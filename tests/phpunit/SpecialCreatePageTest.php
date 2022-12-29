<?php

/*
	Extension:CreatedPageUw - MediaWiki extension.
	Copyright (C) 2018-2022 Edward Chernenko.

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
 * Checks [[Special:CreatePage]] special page.
 */

/**
 * @covers MediaWiki\CreatePageUw\SpecialCreatePage
 * @group Database
 */
class SpecialCreatePageTest extends SpecialPageTestBase {
	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CreatePage' );
	}

	public function needsDB() {
		// Needs existing page to be precreated in addCoreDBData()
		return true;
	}

	/**
	 * Checks the form when Special:CreatePage is opened.
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::getFormFields
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::alterForm
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
	 * Checks redirect to the edit form when Special:CreatePage is submitted.
	 * @param array $opts
	 *
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::onSubmit
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::getEditURL
	 * @note The redirect happens only when selected Title doesn't exist.
	 * @dataProvider editorTypeAndSubpageDataProvider
	 */
	public function testSubmitRedirect( array $opts ) {
		$useVisualEditor = $opts['useVisualEditor'] ?? false;
		$subpage = $opts['subpage'] ?? '';
		$enteredText = $opts['enteredText'] ?? 'some non-existent page';
		$expectedTitle = Title::newFromText( $opts['expectedPageName'] ?? $enteredText );

		$this->setMwGlobals( 'wgCreatePageUwUseVE', $useVisualEditor );

		list( $html, $fauxResponse ) = $this->runSpecial(
			[ 'wpTitle' => $enteredText ],
			true,
			$subpage
		);

		$this->assertSame( '', $html,
			'Special:CreatePage printed some content instead of a redirect.' );

		# Check the Location header
		$location = $fauxResponse->getHeader( 'location' );
		$this->assertNotNull( $location,
			'Special:CreatePage: there is no Location header.' );

		$expectedLocation = wfExpandUrl( $this->getExpectedURL(
			$expectedTitle,
			$useVisualEditor
		) );
		$this->assertEquals( $expectedLocation, $location,
			'Special:CreatePage: unexpected value of Location header.' );
	}

	/**
	 * Data provider for testSubmitRedirect().
	 */
	public function editorTypeAndSubpageDataProvider() {
		return [
			'edit in VisualEditor' => [ [ 'useVisualEditor' => true ] ],
			'normal editor, no subpage' => [ [] ],
			'normal editor, subpage /Template' => [ [
				'subpage' => 'Template',
				'enteredText' => 'name of non-existent template',
				'expectedPageName' => 'Template:Name of non-existent template'
			] ],
			'normal editor, subpage /template (in lowercase)' => [ [
				'subpage' => 'template',
				'enteredText' => 'name of non-existent template',
				'expectedPageName' => 'Template:Name of non-existent template'
			] ],
			'normal editor, subpage /Template, but title has explicit valid prefix Category:' => [ [
				'subpage' => 'Template',
				'enteredText' => 'category:pages where subpage was ignored',
				'expectedPageName' => 'Category:Pages where subpage was ignored'
			] ],
			'normal editor, subpage /Template, title has \':\', but not an existing namespace prefix' => [ [
				'subpage' => 'Category',
				'enteredText' => 'Cats:Serval',
				'expectedPageName' => 'Category:Cats:Serval'
			] ]
		];
	}

	/**
	 * Checks "this page already exists" message when Special:CreatePage is submitted.
	 * @param bool $useVisualEditor
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::onSubmit
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::getEditURL
	 * @dataProvider editorTypeDataProvider
	 */
	public function testSubmitExisting( $useVisualEditor ) {
		# Existing page is pre-created by MediaWikiIntegrationTestCase::addCoreDBData()
		$pageName = 'UTPage';
		$this->setMwGlobals( 'wgCreatePageUwUseVE', $useVisualEditor );

		list( $html, $fauxResponse ) = $this->runSpecial(
			[ 'wpTitle' => $pageName ],
			true
		);

		$location = $fauxResponse->getHeader( 'location' );
		$this->assertNull( $location,
			'Special:CreatePage unexpectedly printed a redirect for an existing page.' );

		$this->assertStringContainsString( "(createpage-titleexists: $pageName)", $html,
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
	 * Data provider for testSubmitExisting().
	 */
	public function editorTypeDataProvider() {
		return [
			"edit in normal editor" => [ false ],
			"edit in VisualEditor" => [ true ]
		];
	}

	/**
	 * Checks "entered title is invalid" situation when Special:CreatePage is submitted.
	 * @covers MediaWiki\CreatePageUw\SpecialCreatePage::onSubmit
	 */
	public function testSubmitInvalidTitle() {
		$this->expectException( MalformedTitleException::class );
		$this->runSpecial(
			[ 'wpTitle' => 'Symbol "[" is not allowed in titles' ],
			true
		);
	}

	/**
	 * Returns expected URL for editing the page $title.
	 * @param Title $title
	 * @param bool $useVisualEditor True for VisualEditor, false for normal editor.
	 * @return string
	 */
	protected function getExpectedURL( Title $title, $useVisualEditor ) {
		return $useVisualEditor ?
			$title->getLocalURL( [ 'veaction' => 'edit' ] ) :
			$title->getEditURL();
	}

	/**
	 * Render Special:CreatePage.
	 * @param array $query Query string parameter.
	 * @param bool $isPosted true for POST request, false for GET request.
	 * @param string $subpage
	 * @return array
	 */
	protected function runSpecial( array $query = [], $isPosted = false, $subpage = '' ) {
		$this->setUserLang( 'qqx' );

		return $this->executeSpecialPage(
			$subpage,
			new FauxRequest( $query, $isPosted )
		);
	}
}
