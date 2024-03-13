<?php

namespace YesWiki\Test\Core\Service;

use Exception;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class Bazar2PublicationActionTest extends YesWikiTestCase
{
    /**
     * @return Wiki
     */
    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertInstanceOf(Wiki::class, $wiki);
        return $wiki;
    }

    /**
     * @depends testWikiExisting
     * @covers Bazar2PublicationAction::run
     * @dataProvider providerTestRun
     * @param string $templatepage
     * @param bool $displayTemplateAlert
     * @param bool $throwException
     * @param Wiki $wiki
     */
    public function testRun(string $templatepage, bool $displayTemplateAlert, bool $throwException, Wiki $wiki)
    {
        $templateString = empty($templatepage) ? '' : " templatepage=\"$templatepage\"";
        $yesWikiString = "{{bazar2publication$templateString}}";

        $exceptionThrown = false;
        try {
            $GLOBALS['wiki'] = $wiki; // because global $wiki could not be defned because the main script is not index.php
            $output = $wiki->Format($yesWikiString);
        } catch (Exception $th) {
            $exceptionThrown = true;
        }

        if ($throwException) {
            $this->assertTrue($exceptionThrown, "Exception should be thrown in Bazar2PublicationActionTest::testRun !");
        } else {
            $this->assertFalse($exceptionThrown, "Exception should not be thrown in Bazar2PublicationActionTest::testRun !");
        }
        $rexExpStr = "/.*".implode('\s*', explode(' ', preg_quote('<div class="alert alert-warning">', '/'))).".*/";
        if ($displayTemplateAlert) {
            $this->assertMatchesRegularExpression($rexExpStr, $output, "templatepage warning should be set");
        } else {
            $this->assertDoesNotMatchRegularExpression($rexExpStr, $output, "templatepage warning should not be set");
        }
    }

    public function providerTestRun()
    {
        return [
            'default behaviour' => [
                'templatepage' => '',
                'templatepage alert displayed' => false,
                'exception thrown' => false
            ],
            'wrong template page' => [
                'templatepage' => ' ',
                'templatepage alert displayed' => true,
                'exception thrown' => false
            ],
            'template from template Page' => [
                'templatepage' => 'PageTitre',  // because PageTitre exists all the time
                'templatepage alert displayed' => false,
                'exception thrown' => false
            ],
        ];
    }
}
