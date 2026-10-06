<?php

namespace App\Support;

class QueryProfileRanking
{
    public function fromLog(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) return [];

        $routes = [];
        $file = new \SplFileObject($path);
        while (! $file->eof()) {
            $line = $file->fgets();
            if (! str_contains($line, 'Local API query profile')) continue;
            $start = strpos($line, '{');
            if ($start === false) continue;
            $entry = json_decode(substr($line, $start), true);
            if (! is_array($entry) || ! isset($entry['route'], $entry['response_ms'], $entry['db_ms'], $entry['query_count'])) continue;

            $route = (string) $entry['route'];
            $current = $routes[$route] ?? ['route' => $route, 'requests' => 0, 'response_ms' => 0.0, 'db_ms' => 0.0, 'query_count' => 0];
            $current['requests']++;
            $current['response_ms'] += (float) $entry['response_ms'];
            $current['db_ms'] += (float) $entry['db_ms'];
            $current['query_count'] += (int) $entry['query_count'];
            $routes[$route] = $current;
        }

        foreach ($routes as &$route) {
            $route['response_ms'] = round($route['response_ms'] / $route['requests'], 2);
            $route['db_ms'] = round($route['db_ms'] / $route['requests'], 2);
            $route['query_count'] = round($route['query_count'] / $route['requests'], 1);
        }
        unset($route);
        usort($routes, fn (array $a, array $b): int => $b['response_ms'] <=> $a['response_ms']);
        return array_slice($routes, 0, 5);
    }
}
