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

namespace GlpiPlugin\Okta\Tests\Unit\Api;

use GlpiPlugin\Okta\Api\OktaClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OktaClient class.
 */
class OktaClientTest extends TestCase
{
    private OktaClient $client;

    protected function setUp(): void
    {
        $this->client = new OktaClient('https://example.okta.com', 'test-api-key');
    }

    public function testConstructorSetsBaseUrl(): void
    {
        $this->assertSame('https://example.okta.com', $this->client->getBaseUrl());
    }

    public function testConstructorTrimsTrailingSlash(): void
    {
        $client = new OktaClient('https://example.okta.com/', 'test-api-key');
        $this->assertSame('https://example.okta.com', $client->getBaseUrl());
    }

    public function testParseLinkHeaderExtractsLinks(): void
    {
        $linkHeader = [
            '<https://example.okta.com/api/v1/groups?after=abc123>; rel="next"',
            '<https://example.okta.com/api/v1/groups>; rel="self"',
        ];

        $links = $this->client->parseLinkHeader($linkHeader);

        $this->assertArrayHasKey('next', $links);
        $this->assertArrayHasKey('self', $links);
        $this->assertSame('https://example.okta.com/api/v1/groups?after=abc123', $links['next']);
        $this->assertSame('https://example.okta.com/api/v1/groups', $links['self']);
    }

    public function testParseLinkHeaderHandlesEmptyArray(): void
    {
        $links = $this->client->parseLinkHeader([]);
        $this->assertSame([], $links);
    }

    public function testParseLinkHeaderHandlesMalformedHeader(): void
    {
        $linkHeader = ['invalid-header-format'];
        $links = $this->client->parseLinkHeader($linkHeader);
        $this->assertSame([], $links);
    }

    public function testParseLinkHeaderDecodesHtmlEntities(): void
    {
        $linkHeader = [
            '<https://example.okta.com/api/v1/groups?param1=a&amp;param2=b>; rel="next"',
        ];

        $links = $this->client->parseLinkHeader($linkHeader);

        $this->assertSame('https://example.okta.com/api/v1/groups?param1=a&param2=b', $links['next']);
    }

    public function testBuildUrlPrependsBaseUrlForRelativePath(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('buildUrl');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, '/api/v1/users');
        
        $this->assertSame('https://example.okta.com/api/v1/users', $result);
    }

    public function testBuildUrlHandlesRelativePathWithoutLeadingSlash(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('buildUrl');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, 'api/v1/users');
        
        $this->assertSame('https://example.okta.com/api/v1/users', $result);
    }

    public function testBuildUrlReturnsAbsoluteUrlUnchanged(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('buildUrl');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, 'https://example.okta.com/api/v1/users?after=abc');
        
        $this->assertSame('https://example.okta.com/api/v1/users?after=abc', $result);
    }

    public function testParseLinkHeaderHandlesMultipleLinks(): void
    {
        $linkHeader = [
            '<https://example.okta.com/api/v1/groups?after=abc>; rel="next"',
            '<https://example.okta.com/api/v1/groups?before=xyz>; rel="prev"',
            '<https://example.okta.com/api/v1/groups>; rel="self"',
        ];

        $links = $this->client->parseLinkHeader($linkHeader);

        $this->assertCount(3, $links);
        $this->assertArrayHasKey('next', $links);
        $this->assertArrayHasKey('prev', $links);
        $this->assertArrayHasKey('self', $links);
    }

    public function testParseLinkHeaderHandlesMissingRel(): void
    {
        $linkHeader = [
            '<https://example.okta.com/api/v1/groups>',  // Missing rel
        ];

        $links = $this->client->parseLinkHeader($linkHeader);

        $this->assertSame([], $links);
    }
}
