<?php

/*
	Extension:CreatedPageUw - MediaWiki extension.
	Copyright (C) 2018 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * @brief Checks [[Special:CreatePage]] special page.
 */

/**
 * @covers SpecialCreatePage
 * @group Database
 */
class SpecialCreatePageTest extends SpecialPageTestBase {
	protected function newSpecialPage() {
		return new SpecialCreatePage();
	}

	public function needsDB() {
		// Needs existing page to be precreated in addCoreDBData()
		return true;
	}

	/**
	 * @brief Checks the form when Special:CreatePage is opened.
	 * @covers SpecialCreatePage::getFormFields
	 * @covers SpecialCreatePage::alterForm
	 */
	public function testForm() {
		list( $html, ) = $this->runSpecial();

		$dom = new DomDocument;
		$dom->loadHTML( $html );

		$xpath = new DomXpath( $dom );
		$form = $xpath->query( '//form[contains(@action,"Special:CreatePage")]' )
			->item( 0 );

		$this->assertNotNull( $form, 'Special:CreatePage: <form> element not found' );

		$legend = $xpath->query( '//form/fieldset/legend', $form )->item( 0 );
		$this->assertNotNull( $legend, 'Special:CreatePage: <legend> not found' );
		$this->assertEquals( '(createpage)', $legend->textContent );

		$input = $xpath->query( '//input[@name="wpTitle"]', $form )->item( 0 );
		$this->assertNotNull( $input,
			'Special:CreatePage: <input name="wpTitle"/> not found' );

		$label = $xpath->query(
				'//label[@for="' . $input->getAttribute( 'id' ) . '"]', $form
			)->item( 0 );
		$this->assertNotNull( $label,
			'Special:CreatePage: <label for="wpTitle"> not found' );
		$this->assertEquals( '(createpage-instructions)', $label->textContent );

		$submit = $xpath->query( '//*[@type="submit"]', $form )->item( 0 );
		$this->assertNotNull( $submit, 'Special:CreatePage: Submit button not found' );
	}

	/**
	 * @brief Checks redirect to the edit form when Special:CreatePage is submitted.
	 * @covers SpecialCreatePage::onSubmit
	 * @covers SpecialCreatePage::getEditURL
	 * @note The redirect happens only when selected Title doesn't exist.
	 * @dataProvider editorTypeDataProvider
	 */
	public function testSubmitRedirect( $useVisualEditor ) {
		$pageName = 'Some non-existent page';
		$this->setMwGlobals( 'wgCreatePageUwUseVE', $useVisualEditor );

		list( $html, $fauxResponse ) = $this->runSpecial(
			[ 'wpTitle' => $pageName ],
			true
		);

		$this->assertSame( '', $html,
			'Special:CreatePage printed some content instead of a redirect.' );

		# Check the Location header
		$location = $fauxResponse->getHeader( 'location' );
		$this->assertNotNull( $location,
			'Special:CreatePage: there is no Location header.' );

		$expectedLocation = wfExpandUrl( $this->getExpectedURL(
			Title::newFromText( $pageName ),
			$useVisualEditor
		) );
		$this->assertEquals( $expectedLocation, $location,
			'Special:CreatePage: unexpected value of Location header.' );
	}

	/**
	 * @brief Checks "this page already exists" message when Special:CreatePage is submitted.
	 * @covers SpecialCreatePage::onSubmit
	 * @covers SpecialCreatePage::getEditURL
	 * @dataProvider editorTypeDataProvider
	 */
	public function testSubmitExisting( $useVisualEditor ) {
		# Existing page is pre-created by MediaWikiTestCase::addCoreDBData()
		$pageName = 'UTPage';
		$this->setMwGlobals( 'wgCreatePageUwUseVE', $useVisualEditor );

		list( $html, $fauxResponse ) = $this->runSpecial(
			[ 'wpTitle' => $pageName ],
			true
		);

		$location = $fauxResponse->getHeader( 'location' );
		$this->assertNull( $location,
			'Special:CreatePage unexpectedly printed a redirect for an existing page.' );

		$this->assertContains( "(createpage-titleexists: $pageName)", $html,
			'Special:CreatePage: no "page already exists" message.' );

		$dom = new DomDocument;
		$dom->loadHTML( $html );

		$xpath = new DomXpath( $dom );
		$editExistingLink = $xpath->query(
			'//a[contains(.,"createpage-editexisting")]' )->item( 0 );
		$this->assertNotNull( $editExistingLink,
			'Special:CreatePage: link "edit existing page" not found.' );

		$this->assertEquals(
			$this->getExpectedURL( Title::newFromText( $pageName ), $useVisualEditor ),
			$editExistingLink->getAttribute( 'href' ),
			'Special:CreatePage: incorrect URL of "edit existing page" link.'
		);

		$tryAgainLink = $xpath->query(
			'//a[contains(.,"createpage-tryagain")]' )->item( 0 );
		$this->assertNotNull( $tryAgainLink,
			'Special:CreatePage: link "try again" not found.' );

		$this->assertEquals(
			SpecialPage::getTitleFor( 'CreatePage' )->getLinkURL(),
			$tryAgainLink->getAttribute( 'href' ),
			'Special:CreatePage: incorrect URL of "try again" link.'
		);
	}

	/**
	 * @brief Returns expected URL for editing the page $title.
	 * @param $useVisualEditor True for VisualEditor, false for normal editor.
	 */
	protected function getExpectedURL( Title $title, $useVisualEditor ) {
		return $useVisualEditor ?
			$title->getLocalURL( [ 'veaction' => 'edit' ] ) :
			$title->getEditURL();
	}

	/**
	 * @brief Data provider for testSubmitRedirect() and testSubmitExisting().
	 */
	public function editorTypeDataProvider() {
		return [
			[ "edit in normal editor", [ false ] ],
			[ "edit in VisualEditor", [ true ] ]
		];
	}

	/**
	 * @brief Render Special:CreatePage.
	 * @param $query Query string parameter.
	 * @param $isPosted true for POST request, false for GET request.
	 * @returns HTML of the result.
	 */
	protected function runSpecial( array $query = [], $isPosted = false ) {
		// HTMLForm sometimes calls wfMessage() without context, so we must set $wgLang
		global $wgLang;
		$wgLang = Language::factory( 'qqx' );

		return $this->executeSpecialPage(
			'',
			new FauxRequest( $query, $isPosted ),
			$wgLang
		);
	}
}
