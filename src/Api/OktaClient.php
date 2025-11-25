<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2025 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of ITSM-NG.
 *
 * ITSM-NG is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ITSM-NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ITSM-NG. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Okta\Api;

use Session;
use Toolbox;
use GlpiPlugin\Okta\PluginOktaConfig;

/**
 * OktaClient handles all HTTP communication with the Okta API.
 */
class OktaClient
{
    private string $baseUrl;
    private string $apiKey;

    /**
     * @param string $baseUrl The Okta API base URL
     * @param string $apiKey  The decrypted API key
     */
    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Create an OktaClient from stored configuration values.
     *
     * @return self|null Returns null if configuration is invalid
     */
    public static function fromConfig(): ?self
    {
        $config = PluginOktaConfig::getConfigValues();
        
        if (empty($config['url']) || empty($config['key'])) {
            return null;
        }

        $apiKey = Toolbox::sodiumDecrypt($config['key']);
        return new self($config['url'], $apiKey);
    }

    /**
     * Make a request to the Okta API.
     *
     * @param string      $uri    The API endpoint (relative or absolute)
     * @param string      $method HTTP method (GET, POST, PUT, DELETE)
     * @param string|null $body   Request body (JSON string)
     *
     * @return array{header: array, body: array}|false Returns response array or false on error
     */
    public function request(string $uri, string $method = 'GET', ?string $body = null)
    {
        $ch = curl_init();
        $responseHeader = [];
        
        $url = $this->buildUrl($uri);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeader) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) == 2) {
                $responseHeader[strtolower(trim($header[0]))][] = trim($header[1]);
            }
            return $len;
        });

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: SSWS ' . $this->apiKey,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseBody = substr($response, $headerSize);

        if (curl_errno($ch)) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API: ' . curl_error($ch)), false, ERROR);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $jsonResponse = json_decode($responseBody, true);

        if (!$response) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API'), false, ERROR);
            return false;
        } else if (isset($jsonResponse['errorCode']) || !$jsonResponse) {
            Session::addMessageAfterRedirect(__('Invalid API key'), false, ERROR);
            return false;
        }

        return ['header' => $responseHeader, 'body' => $jsonResponse];
    }

    /**
     * Build full URL from relative or absolute URI.
     *
     * @param string $uri The URI to process
     *
     * @return string The full URL
     */
    private function buildUrl(string $uri): string
    {
        if (strpos($uri, $this->baseUrl) === false) {
            return $this->baseUrl . '/' . ltrim($uri, '/');
        }
        return $uri;
    }

    /**
     * Parse Link header for pagination.
     *
     * @param array $linkHeader Array of Link header values
     *
     * @return array Associative array of rel => url
     */
    public function parseLinkHeader(array $linkHeader): array
    {
        $links = [];
        foreach ($linkHeader as $part) {
            if (preg_match('/<(.*?)>;\s*rel="(.*?)"/', $part, $matches)) {
                $matches[1] = html_entity_decode($matches[1]);
                $links[$matches[2]] = $matches[1];
            }
        }
        return $links;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
