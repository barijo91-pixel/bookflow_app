<?php

namespace App\Http\Controllers;

class SitemapController extends Controller
{
    public function index()
    {
        $now = now()->toIso8601String();
        $base = rtrim(config('app.url'), '/');

        $urls = [
            ['loc' => $base.'/',         'changefreq' => 'weekly',  'priority' => '1.0'],
            ['loc' => $base.'/login',    'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => $base.'/register', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$u['loc']}</loc>\n";
            $xml .= "    <lastmod>{$now}</lastmod>\n";
            $xml .= "    <changefreq>{$u['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$u['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
