<?php

declare(strict_types=1);

use Capell\SiteSearch\Tests\SiteSearchTestCase;

pest()->extend(SiteSearchTestCase::class)->group('site-search')->in(__DIR__);
