<?php

class Modern_NetBeez_API
{
    private const MAX_PAGES = 100;
    private const JSONAPI_PAGE_SIZE = 200;
    private const BATCH_SIZE = 20;
    private const TEMPLATE_CACHE_TTL = 600;
    private const HOMEPAGE_CACHE_TTL = 90;

    public static function resolveConfig(): array
    {
        self::loadConfigFromFiles();

        $host = self::firstDefinedConstant(['API_HOST', 'NETBEEZ_BASE_URL', 'BASE_URL']);
        if ($host === '') {
            $host = self::firstEnv(['NETBEEZ_BASE_URL', 'API_HOST', 'NB_API_HOST', 'NETBEEZ_HOST']);
        }

        $key = self::firstDefinedConstant(['API_AUTH_KEY', 'API_KEY', 'NETBEEZ_API_KEY', 'NB_API_KEY']);
        if ($key === '') {
            $key = self::firstEnv(['NETBEEZ_API_KEY', 'API_AUTH_KEY', 'API_KEY', 'NB_API_KEY']);
        }

        $apiVersion = self::firstDefinedConstant(['API_VERSION', 'NETBEEZ_API_VERSION']);
        if ($apiVersion === '') {
            $apiVersion = self::firstEnv(['API_VERSION', 'NETBEEZ_API_VERSION', 'NB_API_VERSION']);
        }

        return [
            'host' => rtrim($host, '/'),
            'key' => $key,
            'api_version' => $apiVersion,
        ];
    }

    public static function buildHomepageSupplementalData(int $timeWindow): array
    {
        $config = self::resolveConfig();
        if ($config['host'] === '' || $config['key'] === '') {
            return [
                'latest_sample_ts' => 0,
                'isp_rows' => [],
                'group_rows' => [],
                'agent_categories' => [],
                'remote_worker_summary' => [
                    'total_quantity' => 0,
                    'total_online' => 0,
                    'total_offline' => 0,
                    'total_with_active_incidents' => 0,
                ],
                'errors' => ['Missing API configuration for supplemental dashboard data.'],
            ];
        }

        $selectedAgentIds = self::loadSelectedAgentIds();
        $cacheKey = 'homepage_' . md5(json_encode([
            'host' => $config['host'],
            'time_window' => $timeWindow,
            'selected_agents' => $selectedAgentIds,
        ]));
        $cached = self::cacheGet($cacheKey, self::HOMEPAGE_CACHE_TTL);
        if (is_array($cached)) {
            if (function_exists('nb_profiler_record')) {
                nb_profiler_record('cache', 'homepage_supplemental:' . $cacheKey, 0, 'HIT');
            }
            return $cached;
        }
        $toMs = (int) round(microtime(true) * 1000);
        $fromMs = $toMs - $timeWindow;
        $windowStartIso = gmdate('Y-m-d\TH:i:s\Z', (int) floor($fromMs / 1000));

        $errors = [];
        $agents = [];
        $networkAgentIds = [];

        try {
            $agentRows = self::apiGetAllPages(
                $config['host'],
                $config['key'],
                '/agents',
                [
                    'type' => 'beta',
                    'page[limit]' => self::JSONAPI_PAGE_SIZE,
                ],
                $config['api_version']
            );

            if ($selectedAgentIds !== null && count($selectedAgentIds) > 0) {
                $wanted = array_fill_keys(array_map('strval', $selectedAgentIds), true);
                $agentRows = array_values(array_filter($agentRows, static function (array $row) use ($wanted): bool {
                    $id = (string) ($row['id'] ?? '');
                    return $id !== '' && isset($wanted[$id]);
                }));
            }

            foreach ($agentRows as $row) {
                $agentId = (int) ($row['id'] ?? 0);
                if ($agentId <= 0) {
                    continue;
                }

                $agent = self::extractAgent(['data' => $row], $agentId);
                $agent['down'] = null;
                $agent['up'] = null;
                $agent['latency'] = null;
                $agent['sample_ts'] = 0;
                $agent['uptime'] = null;
                $agents[$agentId] = $agent;

                if ($agent['category'] === 'network_agent') {
                    $networkAgentIds[] = $agentId;
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }

        if (count($agents) === 0) {
            return [
                'latest_sample_ts' => 0,
                'isp_rows' => [],
                'group_rows' => [],
                'agent_categories' => [],
                'remote_worker_summary' => [
                    'total_quantity' => 0,
                    'total_online' => 0,
                    'total_offline' => 0,
                    'total_with_active_incidents' => 0,
                ],
                'errors' => $errors,
            ];
        }

        $latestSampleTs = 0;
        foreach (self::fetchLatestSpeedByAgent($config, $windowStartIso, array_keys($agents)) as $agentId => $speed) {
            if (!isset($agents[$agentId])) {
                continue;
            }

            $agents[$agentId]['down'] = $speed['down'];
            $agents[$agentId]['up'] = $speed['up'];
            $agents[$agentId]['latency'] = $speed['latency'];
            $agents[$agentId]['sample_ts'] = $speed['ts'];
            $latestSampleTs = max($latestSampleTs, (int) $speed['ts']);
        }

        $uptimeMap = self::fetchLatestUptimeMap($config, $networkAgentIds);
        foreach ($uptimeMap as $agentId => $uptime) {
            if (isset($agents[$agentId])) {
                $agents[$agentId]['uptime'] = $uptime;
            }
        }

        $agentGroups = self::resolveAgentGroups($config, array_keys($agents));
        $agentCategories = [];
        $remoteWorkerIds = [];
        foreach ($agents as $agentId => $agent) {
            $agentCategories[$agentId] = $agent['category'];
            if ($agent['category'] === 'remote_worker_agent') {
                $remoteWorkerIds[] = (int) $agentId;
            }
        }
        $remoteWorkerSummary = self::buildRemoteWorkerSummary($agents, $remoteWorkerIds, $config);

        $result = [
            'latest_sample_ts' => $latestSampleTs,
            'isp_rows' => self::buildIspSummaryRows($agents),
            'group_rows' => self::buildGroupSummaryRows($agents, $agentGroups),
            'agent_categories' => $agentCategories,
            'remote_worker_summary' => $remoteWorkerSummary,
            'errors' => $errors,
        ];

        self::cachePut($cacheKey, $result);
        return $result;
    }

    public static function fetchTargetDetail(int $targetId): ?array
    {
        $config = self::resolveConfig();
        if ($config['host'] === '' || $config['key'] === '' || $targetId <= 0) {
            return null;
        }

        $candidates = [
            ['path' => '/targets/' . $targetId, 'query' => ['type' => 'beta']],
            ['path' => '/targets/' . $targetId . '.json', 'query' => []],
            ['path' => '/nb_targets/' . $targetId . '.json', 'query' => []],
        ];

        foreach ($candidates as $candidate) {
            $response = self::request(
                $config['host'],
                $config['key'],
                $candidate['path'],
                $candidate['query'],
                $config['api_version']
            );

            if (isset($response['__error'])) {
                continue;
            }

            $source = $response['data'] ?? $response['target'] ?? $response['nb_target'] ?? $response;
            if (!is_array($source)) {
                continue;
            }

            $name = self::payloadValue($source, [
                ['attributes', 'name'],
                ['name'],
            ], null);

            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            $description = self::payloadValue($source, [
                ['attributes', 'description'],
                ['description'],
                ['attributes', 'url'],
                ['url'],
                ['attributes', 'fqdn'],
                ['fqdn'],
            ], 'Application response metrics');

            return [
                'id' => $targetId,
                'name' => trim($name),
                'description' => is_string($description) && trim($description) !== '' ? trim($description) : 'Application response metrics',
                'agent_ids' => self::extractTargetAgentIds($source),
            ];
        }

        return null;
    }

    private static function buildIspSummaryRows(array $agents): array
    {
        $ispSummary = [];

        foreach ($agents as $agent) {
            $isp = $agent['isp'] !== '' ? $agent['isp'] : 'Unknown ISP';

            if (!isset($ispSummary[$isp])) {
                $ispSummary[$isp] = [
                    'isp' => $isp,
                    'agent_count' => 0,
                    'online_count' => 0,
                    'offline_count' => 0,
                    'downloads' => [],
                    'uploads' => [],
                    'latencies' => [],
                    'uptimes' => [],
                ];
            }

            $ispSummary[$isp]['agent_count']++;
            if ($agent['active']) {
                $ispSummary[$isp]['online_count']++;
            } else {
                $ispSummary[$isp]['offline_count']++;
            }

            if ($agent['down'] !== null) {
                $ispSummary[$isp]['downloads'][] = $agent['down'];
            }
            if ($agent['up'] !== null) {
                $ispSummary[$isp]['uploads'][] = $agent['up'];
            }
            if ($agent['latency'] !== null) {
                $ispSummary[$isp]['latencies'][] = $agent['latency'];
            }
            if ($agent['uptime'] !== null) {
                $ispSummary[$isp]['uptimes'][] = $agent['uptime'];
            }
        }

        foreach ($ispSummary as &$summary) {
            $summary['avg_download'] = self::average($summary['downloads']);
            $summary['avg_upload'] = self::average($summary['uploads']);
            $summary['avg_latency'] = self::average($summary['latencies']);
            $summary['avg_uptime'] = self::average($summary['uptimes']);
        }
        unset($summary);

        $rows = array_values($ispSummary);
        usort($rows, static function (array $left, array $right): int {
            $downloadCompare = ($right['avg_download'] ?? -1) <=> ($left['avg_download'] ?? -1);
            if ($downloadCompare !== 0) {
                return $downloadCompare;
            }

            return strcmp($left['isp'], $right['isp']);
        });

        return $rows;
    }

    private static function buildGroupSummaryRows(array $agents, array $agentGroups): array
    {
        $summary = [];

        foreach ($agents as $agentId => $agent) {
            $groups = $agentGroups[$agentId] ?? ['Ungrouped'];

            foreach ($groups as $groupName) {
                if (!isset($summary[$groupName])) {
                    $summary[$groupName] = [
                        'group' => $groupName,
                        'agent_count' => 0,
                        'online_count' => 0,
                        'offline_count' => 0,
                        'downloads' => [],
                        'uploads' => [],
                        'latencies' => [],
                        'uptimes' => [],
                    ];
                }

                $summary[$groupName]['agent_count']++;
                if ($agent['active']) {
                    $summary[$groupName]['online_count']++;
                } else {
                    $summary[$groupName]['offline_count']++;
                }

                if ($agent['down'] !== null) {
                    $summary[$groupName]['downloads'][] = $agent['down'];
                }
                if ($agent['up'] !== null) {
                    $summary[$groupName]['uploads'][] = $agent['up'];
                }
                if ($agent['latency'] !== null) {
                    $summary[$groupName]['latencies'][] = $agent['latency'];
                }
                if ($agent['uptime'] !== null) {
                    $summary[$groupName]['uptimes'][] = $agent['uptime'];
                }
            }
        }

        foreach ($summary as &$row) {
            $row['avg_download'] = self::average($row['downloads']);
            $row['avg_upload'] = self::average($row['uploads']);
            $row['avg_latency'] = self::average($row['latencies']);
            $row['avg_uptime'] = self::average($row['uptimes']);
        }
        unset($row);

        $rows = array_values($summary);
        usort($rows, static function (array $left, array $right): int {
            $agentCompare = ($right['agent_count'] ?? 0) <=> ($left['agent_count'] ?? 0);
            if ($agentCompare !== 0) {
                return $agentCompare;
            }

            return strcmp($left['group'], $right['group']);
        });

        return $rows;
    }

    private static function buildRemoteWorkerSummary(array $agents, array $remoteWorkerIds, array $config): array
    {
        $summary = [
            'total_quantity' => count($remoteWorkerIds),
            'total_online' => 0,
            'total_offline' => 0,
            'total_with_active_incidents' => 0,
        ];

        foreach ($remoteWorkerIds as $agentId) {
            if (!isset($agents[$agentId])) {
                continue;
            }

            if (!empty($agents[$agentId]['active'])) {
                $summary['total_online']++;
            } else {
                $summary['total_offline']++;
            }
        }

        if (count($remoteWorkerIds) > 0) {
            $summary['total_with_active_incidents'] = self::fetchActiveIncidentAgentCount($config, $remoteWorkerIds, $agents);
        }

        return $summary;
    }

    private static function fetchActiveIncidentAgentCount(array $config, array $agentIds, array $agents = []): int
    {
        $agentIds = array_values(array_unique(array_map('intval', $agentIds)));
        if (count($agentIds) === 0) {
            return 0;
        }

        $candidates = [
            [
                'path' => '/incidents',
                'paged' => true,
                'query' => [
                    'type' => 'beta',
                    'filter[agents]' => implode(',', $agentIds),
                    'filter[end_ts][operator]' => '=',
                    'filter[end_ts][value1]' => 0,
                    'page[limit]' => self::JSONAPI_PAGE_SIZE,
                ],
            ],
            [
                'path' => '/incidents',
                'paged' => true,
                'query' => [
                    'type' => 'beta',
                    'filter[agents]' => implode(',', $agentIds),
                    'page[limit]' => self::JSONAPI_PAGE_SIZE,
                ],
            ],
            [
                'path' => '/incidents.json',
                'paged' => false,
                'query' => [
                    'filter_agents' => implode(',', $agentIds),
                    'filter_end_ts_operator' => '=',
                    'filter_end_ts_value1' => 0,
                    'page_size' => self::JSONAPI_PAGE_SIZE,
                ],
            ],
            [
                'path' => '/nb_incidents.json',
                'paged' => false,
                'query' => [
                    'filter_agents' => implode(',', $agentIds),
                    'filter_end_ts_operator' => '=',
                    'filter_end_ts_value1' => 0,
                    'page_size' => self::JSONAPI_PAGE_SIZE,
                ],
            ],
        ];

        foreach ($candidates as $candidate) {
            $rows = $candidate['paged']
                ? self::apiGetAllPages($config['host'], $config['key'], $candidate['path'], $candidate['query'], $config['api_version'])
                : self::extractIncidentRows(self::request($config['host'], $config['key'], $candidate['path'], $candidate['query'], $config['api_version']));

            if (!is_array($rows) || count($rows) === 0) {
                continue;
            }

            $activeAgentIds = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (!self::isIncidentActive($row)) {
                    continue;
                }

                foreach (self::extractIncidentAgentIds($row) as $agentId) {
                    if (in_array($agentId, $agentIds, true)) {
                        $activeAgentIds[$agentId] = true;
                    }
                }
            }

            if (count($activeAgentIds) > 0) {
                return count($activeAgentIds);
            }
        }

        return self::fallbackOpenIncidentCount($agents, $agentIds);
    }

    private static function fetchLatestSpeedByAgent(array $config, string $windowStartIso, array $selectedAgentIds): array
    {
        $templateIds = self::resolveSpeedTemplateIds($config);
        if (count($templateIds) === 0 || count($selectedAgentIds) === 0) {
            return [];
        }

        $selectedLookup = array_fill_keys(array_map('strval', $selectedAgentIds), true);
        $latestByAgent = [];

        foreach ($templateIds as $templateId) {
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $response = self::request(
                    $config['host'],
                    $config['key'],
                    '/scheduled_nb_test_templates/' . $templateId . '/results',
                    [
                        'type' => 'beta',
                        'filter[ts][operator]' => '>=',
                        'filter[ts][value1]' => $windowStartIso,
                        'order[attributes]' => 'ts',
                        'order[direction]' => 'desc',
                        'page[limit]' => self::JSONAPI_PAGE_SIZE,
                        'page[offset]' => $page,
                    ],
                    $config['api_version']
                );

                if (isset($response['__error'])) {
                    break;
                }

                $results = $response['data'] ?? [];
                if (!is_array($results) || count($results) === 0) {
                    break;
                }

                foreach ($results as $row) {
                    $agentId = (int) self::payloadValue($row, [['relationships', 'agent', 'data', 'id']], 0);
                    if ($agentId <= 0 || !isset($selectedLookup[(string) $agentId]) || isset($latestByAgent[$agentId])) {
                        continue;
                    }

                    $sample = self::extractSpeedSample(['data' => [$row]]);
                    if ($sample !== null) {
                        $latestByAgent[$agentId] = $sample;
                    }
                }

                if (count($latestByAgent) >= count($selectedLookup)) {
                    return $latestByAgent;
                }

                $totalPages = (int) ($response['meta']['page']['total'] ?? 1);
                if ($page >= $totalPages) {
                    break;
                }
            }
        }

        return $latestByAgent;
    }

    private static function resolveSpeedTemplateIds(array $config): array
    {
        $cacheKey = 'speed_templates_' . md5(json_encode([
            'host' => $config['host'],
            'api_version' => $config['api_version'],
        ]));
        $cached = self::cacheGet($cacheKey, self::TEMPLATE_CACHE_TTL);
        if (is_array($cached)) {
            if (function_exists('nb_profiler_record')) {
                nb_profiler_record('cache', 'speed_templates:' . $cacheKey, 0, 'HIT');
            }
            return $cached;
        }

        $templateRows = self::apiGetAllPages(
            $config['host'],
            $config['key'],
            '/scheduled_nb_test_templates',
            [
                'type' => 'beta',
                'page[limit]' => self::JSONAPI_PAGE_SIZE,
            ],
            $config['api_version']
        );

        $templateIds = [];
        foreach ($templateRows as $row) {
            if ((int) (($row['attributes']['test_type_id'] ?? 0)) === 7) {
                $templateIds[] = (string) $row['id'];
            }
        }

        self::cachePut($cacheKey, $templateIds);
        return $templateIds;
    }

    private static function fetchLatestUptimeMap(array $config, array $agentIds): array
    {
        if (count($agentIds) === 0) {
            return [];
        }

        $paths = [
            '/nb_agent_statistics.json',
            '/v1/nb_agent_statistics.json',
            '/apis/nb_agent_statistics.json',
            '/apis/v1/nb_agent_statistics.json',
        ];

        foreach ($paths as $path) {
            $requests = [];
            foreach ($agentIds as $agentId) {
                $requests['uptime_' . $agentId] = [
                    'endpoint' => $path,
                    'query' => [
                        'agent_id' => $agentId,
                        'last' => 1,
                    ],
                ];
            }

            $responses = self::multiRequest($config['host'], $config['key'], $requests, $config['api_version']);
            $map = [];
            foreach ($responses as $payload) {
                $rows = $payload['agent_stats'] ?? [];
                if (!is_array($rows) || count($rows) === 0) {
                    continue;
                }

                $row = $rows[0];
                $agentId = (int) self::payloadValue($row, [['agent_id']], 0);
                $uptime = self::payloadValue($row, [['uptime']], null);
                if ($agentId > 0 && is_numeric($uptime)) {
                    $map[$agentId] = (float) $uptime;
                }
            }

            if (count($map) > 0) {
                return $map;
            }
        }

        return [];
    }

    private static function resolveAgentGroups(array $config, array $agentIds): array
    {
        $inventory = self::fetchAgentGroupInventory($config);
        $groupIdToName = [];
        $byAgent = [];

        foreach ($inventory as $group) {
            $groupIdToName[(int) $group['id']] = (string) $group['name'];
            foreach (($group['agent_ids'] ?? []) as $agentId) {
                $byAgent[(int) $agentId][] = (string) $group['name'];
            }
        }

        foreach ($byAgent as $agentId => $names) {
            $byAgent[$agentId] = self::normalizeGroupNames($names);
        }

        $detailGroups = self::fetchAgentGroupsFromAgentDetails($config, $agentIds, $groupIdToName);
        foreach ($detailGroups as $agentId => $names) {
            $byAgent[$agentId] = self::normalizeGroupNames(array_merge($byAgent[$agentId] ?? [], $names));
        }

        foreach ($agentIds as $agentId) {
            if (!isset($byAgent[$agentId]) || count($byAgent[$agentId]) === 0) {
                $byAgent[$agentId] = ['Ungrouped'];
            }
        }

        return $byAgent;
    }

    private static function fetchAgentGroupInventory(array $config): array
    {
        $candidates = [
            ['path' => '/agent_groups', 'query' => ['type' => 'beta', 'include' => 'agents']],
            ['path' => '/groups', 'query' => ['type' => 'beta', 'include' => 'agents']],
            ['path' => '/agent_groups.json', 'query' => []],
            ['path' => '/groups.json', 'query' => []],
        ];

        foreach ($candidates as $candidate) {
            $response = self::request(
                $config['host'],
                $config['key'],
                $candidate['path'],
                $candidate['query'],
                $config['api_version']
            );

            if (isset($response['__error'])) {
                continue;
            }

            $rawRows = $response['data'] ?? $response['agent_groups'] ?? $response['groups'] ?? $response;
            if (!is_array($rawRows)) {
                continue;
            }

            $rows = self::isAssocArray($rawRows) ? [$rawRows] : $rawRows;
            $groups = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $source = $row['data'] ?? $row;
                $groupId = self::extractGroupId($source);
                $groupName = self::extractGroupName($source);
                if ($groupId === null || $groupName === null) {
                    continue;
                }

                $groups[$groupId] = [
                    'id' => $groupId,
                    'name' => $groupName,
                    'agent_ids' => self::extractAgentIdsFromGroupPayload($row),
                ];
            }

            if (count($groups) > 0) {
                return $groups;
            }
        }

        return [];
    }

    private static function fetchAgentGroupsFromAgentDetails(array $config, array $agentIds, array $groupIdToName): array
    {
        $headers = self::apiHeaders($config['key'], $config['api_version']);
        $byAgent = [];

        foreach (array_chunk($agentIds, self::BATCH_SIZE) as $chunk) {
            $requests = [];
            foreach ($chunk as $agentId) {
                $requests['agent_' . $agentId] = rtrim($config['host'], '/') . '/agents/' . $agentId . '.json';
            }

            $responses = self::multiRequestUrls($requests, $headers);
            foreach ($responses as $key => $payload) {
                if (isset($payload['__error'])) {
                    continue;
                }

                $agentId = (int) str_replace('agent_', '', $key);
                $groups = self::extractAgentGroupsFromAgentDetail($payload, $groupIdToName);
                if (count($groups) > 0) {
                    $byAgent[$agentId] = $groups;
                }
            }
        }

        return $byAgent;
    }

    private static function loadConfigFromFiles(): void
    {
        $roots = array_values(array_unique(array_filter([
            dirname(__DIR__),
            __DIR__,
            getcwd() ?: null,
            isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : null,
            isset($_SERVER['SCRIPT_FILENAME']) ? dirname((string) $_SERVER['SCRIPT_FILENAME']) : null,
        ])));

        foreach ($roots as $root) {
            foreach ([
                rtrim($root, '/') . '/config.php',
                rtrim($root, '/') . '/inc/config.php',
                rtrim($root, '/') . '/includes/config.php',
                rtrim($root, '/') . '/public-dashboard/config.php',
                rtrim($root, '/') . '/public-dashboard/inc/config.php',
                rtrim($root, '/') . '/public-dashboard/includes/config.php',
            ] as $file) {
                if (is_file($file) && is_readable($file)) {
                    require_once $file;
                }
            }

            self::loadEnvFileIfPresent(rtrim($root, '/') . '/.env');
            self::loadEnvFileIfPresent(rtrim($root, '/') . '/.env.local');
        }
    }

    private static function loadEnvFileIfPresent(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            $length = strlen($value);
            $quoted = $length >= 2 && (
                ($value[0] === '"' && $value[$length - 1] === '"') ||
                ($value[0] === "'" && $value[$length - 1] === "'")
            );
            if ($quoted) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    private static function firstDefinedConstant(array $names): string
    {
        foreach ($names as $name) {
            if (defined($name)) {
                $value = trim((string) constant($name));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private static function firstEnv(array $names): string
    {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    private static function loadSelectedAgentIds(): ?array
    {
        $candidates = [
            dirname(__DIR__) . '/admin/settings.json',
            dirname(__DIR__) . '/public-dashboard/admin/settings.json',
            dirname(__DIR__) . '/settings.json',
            dirname(dirname(__DIR__)) . '/public-dashboard/admin/settings.json',
        ];

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($candidate), true);
            if (!is_array($decoded)) {
                continue;
            }

            $selected = $decoded['selected_agents'] ?? null;
            if (is_array($selected) && count($selected) > 0) {
                return array_values(array_unique(array_map('intval', $selected)));
            }
        }

        return null;
    }

    private static function apiHeaders(string $apiKey, string $apiVersion = ''): array
    {
        return array_values(array_filter([
            'Accept: application/json',
            $apiVersion !== '' ? ('Authorization: ' . $apiKey) : null,
            $apiVersion !== '' ? ('API-VERSION: ' . $apiVersion) : null,
            $apiVersion === '' ? ('Authorization: Bearer ' . $apiKey) : null,
        ]));
    }

    private static function buildUrl(string $host, string $path, array $query = []): string
    {
        $url = rtrim($host, '/') . $path;
        $queryString = http_build_query($query);
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    private static function request(string $host, string $apiKey, string $path, array $query = [], string $apiVersion = ''): array
    {
        return self::requestUrl(self::buildUrl($host, $path, $query), self::apiHeaders($apiKey, $apiVersion));
    }

    private static function requestUrl(string $url, array $headers): array
    {
        $startTime = microtime(true);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => (defined('SSL_VERIFY_HOST') && SSL_VERIFY_HOST) ? 2 : 0,
            CURLOPT_SSL_VERIFYPEER => (defined('SSL_VERIFY_PEER') && SSL_VERIFY_PEER) ? 1 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (function_exists('nb_profiler_record')) {
            nb_profiler_record('modern', $url, (microtime(true) - $startTime) * 1000, $httpCode);
        }

        if ($response === false || $httpCode >= 400) {
            return [
                '__error' => $error !== '' ? $error : ('HTTP ' . $httpCode),
                '__http_code' => $httpCode,
                '__url' => $url,
            ];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function multiRequest(string $host, string $apiKey, array $requests, string $apiVersion = ''): array
    {
        $urls = [];
        foreach ($requests as $key => $request) {
            $urls[$key] = self::buildUrl($host, $request['endpoint'], $request['query'] ?? []);
        }

        return self::multiRequestUrls($urls, self::apiHeaders($apiKey, $apiVersion));
    }

    private static function multiRequestUrls(array $urls, array $headers): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $responses = [];

        foreach ($urls as $key => $url) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYHOST => (defined('SSL_VERIFY_HOST') && SSL_VERIFY_HOST) ? 2 : 0,
                CURLOPT_SSL_VERIFYPEER => (defined('SSL_VERIFY_PEER') && SSL_VERIFY_PEER) ? 1 : 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 60,
            ]);

            curl_multi_add_handle($multi, $curl);
            $handles[$key] = array(
                'curl' => $curl,
                'url' => $url,
                'started_at' => microtime(true),
            );
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $key => $handleInfo) {
            $curl = $handleInfo['curl'];
            $body = curl_multi_getcontent($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            if (function_exists('nb_profiler_record')) {
                nb_profiler_record('modern', $handleInfo['url'], (microtime(true) - $handleInfo['started_at']) * 1000, $httpCode);
            }

            if ($body === false || $httpCode >= 400) {
                $responses[$key] = [
                    '__error' => $error !== '' ? $error : ('HTTP ' . $httpCode),
                    '__http_code' => $httpCode,
                ];
            } else {
                $decoded = json_decode($body, true);
                $responses[$key] = is_array($decoded) ? $decoded : [];
            }

            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
        }

        curl_multi_close($multi);
        return $responses;
    }

    private static function apiGetAllPages(string $host, string $apiKey, string $path, array $baseQuery = [], string $apiVersion = ''): array
    {
        $all = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $query = $baseQuery;
            $query['page[offset]'] = $page;
            $query['page[limit]'] = $query['page[limit]'] ?? self::JSONAPI_PAGE_SIZE;

            $response = self::request($host, $apiKey, $path, $query, $apiVersion);
            if (isset($response['__error'])) {
                break;
            }

            $rows = $response['data'] ?? [];
            if (!is_array($rows) || count($rows) === 0) {
                break;
            }

            $all = array_merge($all, $rows);
            $totalPages = (int) ($response['meta']['page']['total'] ?? 1);
            if ($page >= $totalPages) {
                break;
            }
        }

        return $all;
    }

    private static function extractAgent(array $payload, int $agentId): array
    {
        $source = $payload['data'] ?? $payload;
        $rawIsp = self::payloadValue(
            $source,
            [
                ['attributes', 'isp_name'],
                ['isp_name'],
                ['attributes', 'isp'],
                ['isp'],
                ['attributes', 'ispName'],
                ['ispName'],
                ['attributes', 'provider'],
                ['provider'],
            ],
            null
        );

        return [
            'id' => $agentId,
            'name' => (string) self::payloadValue($source, [['attributes', 'name'], ['name']], 'Agent ' . $agentId),
            'category' => (string) self::payloadValue($source, [['attributes', 'category'], ['category']], 'unknown'),
            'isp' => is_string($rawIsp) && trim($rawIsp) !== '' ? trim($rawIsp) : 'Unknown ISP',
            'agent_class' => (string) self::payloadValue($source, [['attributes', 'agent_class'], ['agent_class']], 'unknown'),
            'active' => (bool) self::payloadValue($source, [['attributes', 'active'], ['active']], false),
            'open_incident_id' => self::payloadValue($source, [['attributes', 'open_incident_id'], ['open_incident_id']], null),
        ];
    }

    private static function extractSpeedSample(array $payload): ?array
    {
        $samples = $payload['nb_test_results'] ?? $payload['data'] ?? [];
        if (!is_array($samples) || count($samples) === 0) {
            return null;
        }

        usort($samples, static function (array $left, array $right): int {
            $leftTs = (int) self::payloadValue($left, [['ts'], ['attributes', 'ts']], 0);
            $rightTs = (int) self::payloadValue($right, [['ts'], ['attributes', 'ts']], 0);
            return $rightTs <=> $leftTs;
        });

        foreach ($samples as $sample) {
            $resultValues = self::payloadValue($sample, [['result_values'], ['attributes', 'result_values']], []);
            if (!is_array($resultValues)) {
                continue;
            }

            $metrics = ['down' => null, 'up' => null, 'latency' => null];
            foreach ($resultValues as $value) {
                if (!is_array($value) || !isset($value['key'])) {
                    continue;
                }

                $key = (string) $value['key'];
                if (array_key_exists($key, $metrics) && isset($value['value']) && is_numeric($value['value'])) {
                    $metrics[$key] = (float) $value['value'];
                }
            }

            if ($metrics['down'] !== null || $metrics['up'] !== null || $metrics['latency'] !== null) {
                return [
                    'ts' => (int) self::payloadValue($sample, [['ts'], ['attributes', 'ts']], 0),
                    'down' => $metrics['down'],
                    'up' => $metrics['up'],
                    'latency' => $metrics['latency'],
                ];
            }
        }

        return null;
    }

    private static function payloadValue(array $payload, array $paths, $default = null)
    {
        foreach ($paths as $path) {
            $current = $payload;
            $found = true;

            foreach ($path as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    $found = false;
                    break;
                }
                $current = $current[$segment];
            }

            if ($found && $current !== null && $current !== '') {
                return $current;
            }
        }

        return $default;
    }

    private static function average(array $values): ?float
    {
        $numeric = array_values(array_filter($values, static function ($value): bool {
            return $value !== null && is_numeric($value);
        }));

        if (count($numeric) === 0) {
            return null;
        }

        return array_sum($numeric) / count($numeric);
    }

    private static function isAssocArray(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private static function normalizeGroupNames(array $names): array
    {
        $normalized = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $normalized[$name] = true;
            }
        }

        $result = array_keys($normalized);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    private static function extractTargetAgentIds(array $source): array
    {
        $ids = [];

        $relationshipAgents = $source['relationships']['agents']['data'] ?? null;
        if (is_array($relationshipAgents)) {
            foreach ($relationshipAgents as $row) {
                $id = $row['id'] ?? null;
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
        }

        foreach (['agent_ids', 'agents'] as $key) {
            if (!isset($source[$key]) || !is_array($source[$key])) {
                continue;
            }

            foreach ($source[$key] as $row) {
                if (is_numeric($row)) {
                    $ids[] = (int) $row;
                    continue;
                }

                if (is_array($row) && is_numeric($row['id'] ?? null)) {
                    $ids[] = (int) $row['id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static function (int $id): bool {
            return $id > 0;
        })));
    }

    private static function extractGroupId($value): ?int
    {
        if (is_array($value)) {
            $candidate = $value['id'] ?? $value['group_id'] ?? ($value['attributes']['id'] ?? null);
            return is_numeric($candidate) ? (int) $candidate : null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function extractGroupName($value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value !== '' ? $value : null;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach ([
            $value['name'] ?? null,
            $value['group_name'] ?? null,
            $value['attributes']['name'] ?? null,
            $value['attributes']['group_name'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private static function groupIdsFromAgentReference($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        if (self::isAssocArray($value)) {
            $agentId = $value['id'] ?? $value['agent_id'] ?? null;
            return is_numeric($agentId) ? [(int) $agentId] : [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $candidate = $item['id'] ?? $item['agent_id'] ?? null;
                if (is_numeric($candidate)) {
                    $ids[] = (int) $candidate;
                }
            } elseif (is_numeric($item)) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function extractAgentIdsFromGroupPayload(array $groupPayload): array
    {
        $source = $groupPayload['data'] ?? $groupPayload['group'] ?? $groupPayload;
        if (!is_array($source)) {
            return [];
        }

        $agentIds = [];
        foreach (['agents', 'agent_ids', 'group_agents'] as $key) {
            if (isset($source[$key])) {
                $agentIds = array_merge($agentIds, self::groupIdsFromAgentReference($source[$key]));
            }
        }

        $relationships = $source['relationships'] ?? [];
        foreach (['agents', 'group_agents'] as $key) {
            $data = $relationships[$key]['data'] ?? null;
            if (is_array($data)) {
                $agentIds = array_merge($agentIds, self::groupIdsFromAgentReference($data));
            }
        }

        return array_values(array_unique(array_filter($agentIds, static function ($id): bool {
            return $id > 0;
        })));
    }

    private static function extractAgentGroupsFromAgentDetail(array $agentDetail, array $groupIdToName): array
    {
        $source = $agentDetail['agent'] ?? $agentDetail['data'] ?? $agentDetail;
        if (!is_array($source)) {
            return [];
        }

        $names = [];

        foreach (['groups', 'agent_groups'] as $key) {
            if (isset($source[$key]) && is_array($source[$key])) {
                foreach ($source[$key] as $value) {
                    $name = self::extractGroupName($value);
                    if ($name !== null) {
                        $names[] = $name;
                        continue;
                    }

                    $groupId = self::extractGroupId($value);
                    if ($groupId !== null && isset($groupIdToName[$groupId])) {
                        $names[] = $groupIdToName[$groupId];
                    }
                }
            }
        }

        foreach (['group_ids', 'agent_group_ids'] as $key) {
            if (isset($source[$key]) && is_array($source[$key])) {
                foreach ($source[$key] as $groupId) {
                    if (is_numeric($groupId) && isset($groupIdToName[(int) $groupId])) {
                        $names[] = $groupIdToName[(int) $groupId];
                    }
                }
            }
        }

        foreach (['group_name', 'agent_group_name'] as $key) {
            if (isset($source[$key]) && is_string($source[$key]) && trim($source[$key]) !== '') {
                $names[] = trim($source[$key]);
            }
        }

        return self::normalizeGroupNames($names);
    }

    private static function extractIncidentRows(array $payload): array
    {
        $rows = $payload['data'] ?? $payload['incidents'] ?? $payload['nb_incidents'] ?? [];
        return is_array($rows) ? $rows : [];
    }

    private static function isIncidentActive(array $row): bool
    {
        $source = $row['data'] ?? $row;
        $endTs = self::payloadValue($source, [
            ['attributes', 'end_ts'],
            ['end_ts'],
            ['attributes', 'endTs'],
            ['endTs'],
        ], null);

        if ($endTs === null || $endTs === '') {
            return true;
        }

        $status = self::payloadValue($source, [
            ['attributes', 'status'],
            ['status'],
        ], null);

        if (is_string($status) && in_array(strtolower(trim($status)), ['open', 'active'], true)) {
            return true;
        }

        return false;
    }

    private static function extractIncidentAgentIds(array $row): array
    {
        $source = $row['data'] ?? $row;
        $ids = [];

        $direct = self::payloadValue($source, [
            ['attributes', 'agent_id'],
            ['agent_id'],
        ], null);
        if (is_numeric($direct)) {
            $ids[] = (int) $direct;
        }

        $relationshipAgent = $source['relationships']['agent']['data']['id'] ?? null;
        if (is_numeric($relationshipAgent)) {
            $ids[] = (int) $relationshipAgent;
        }

        $relationshipAgents = $source['relationships']['agents']['data'] ?? null;
        if (is_array($relationshipAgents)) {
            foreach ($relationshipAgents as $agent) {
                if (is_array($agent) && is_numeric($agent['id'] ?? null)) {
                    $ids[] = (int) $agent['id'];
                }
            }
        }

        foreach (['agent_ids', 'agents'] as $key) {
            if (!isset($source[$key]) || !is_array($source[$key])) {
                continue;
            }

            foreach ($source[$key] as $agent) {
                if (is_numeric($agent)) {
                    $ids[] = (int) $agent;
                } elseif (is_array($agent) && is_numeric($agent['id'] ?? null)) {
                    $ids[] = (int) $agent['id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static function ($id): bool {
            return $id > 0;
        })));
    }

    private static function fallbackOpenIncidentCount(array $agents, array $agentIds): int
    {
        $count = 0;

        foreach ($agentIds as $agentId) {
            if (!isset($agents[$agentId])) {
                continue;
            }

            $openIncidentId = $agents[$agentId]['open_incident_id'] ?? null;
            if (is_numeric($openIncidentId) && (int) $openIncidentId > 0) {
                $count++;
            }
        }

        return $count;
    }

    private static function cacheDirectory(): string
    {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public_dashboard_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private static function cachePath(string $key): string
    {
        return self::cacheDirectory() . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $key) . '.json';
    }

    private static function cacheGet(string $key, int $ttlSeconds)
    {
        $path = self::cachePath($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['created_at']) || !array_key_exists('payload', $decoded)) {
            return null;
        }

        if ((time() - (int) $decoded['created_at']) > $ttlSeconds) {
            return null;
        }

        return $decoded['payload'];
    }

    private static function cachePut(string $key, $payload): void
    {
        @file_put_contents(self::cachePath($key), json_encode([
            'created_at' => time(),
            'payload' => $payload,
        ]));
    }
}
