<?php

/**
 * @file
 * @brief Implements [[Special:CreatePage]].
 *
 * We can link to Special:CreatePage when user asks "Where do I create a new page?".
 *
 * Note: this is a modern port of "Uniwiki CreatePage" extension (obsolete in 2012).
 * Please see the original extension for authors.
 */

namespace MediaWiki\CreatePageUw;

use FormSpecialPage;
use HTMLForm;
use Linker;
use NamespaceInfo;
use Status;
use Title;
use Xml;

class SpecialCreatePage extends FormSpecialPage {

	/** @var NamespaceInfo */
	protected $namespaceInfo;

	/**
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct( NamespaceInfo $namespaceInfo ) {
		parent::__construct( 'CreatePage', 'createpage' );

		$this->namespaceInfo = $namespaceInfo;
	}

	protected function getFormFields() {
		return [
			'Title' => [
				'id' => 'mw-input-wptitle',
				'type' => 'text',
				'label-message' => 'createpage-instructions'
			]
		];
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegendMsg( 'createpage' );
	}

	protected function getEditURL( Title $title ) {
		global $wgCreatePageUwUseVE;
		if ( $wgCreatePageUwUseVE ) {
			return $title->getLocalURL( [
				'veaction' => 'edit'
			] );
		}

		return $title->getEditURL();
	}

	public function onSubmit( array $params ) {
		$out = $this->getOutput();

		$pageName = $params['Title'];
		if ( !$pageName ) {
			$out->redirect( $this->getPageTitle()->getFullURL() );
			return Status::newGood();
		}

		// If the user has visited [[Special:CreatePage/Category]] or [[Special:CreatePage/Template]],
		// and the user hasn't typed an explicit valid prefix (e.g. "Talk:Something"),
		// then new page should be created in Category: or Template: namespace respectively.
		$namespaceIndex = $this->namespaceInfo->getCanonicalIndex( strtolower( $this->par ) ) ?? NS_MAIN;

		$title = Title::newFromTextThrow( $pageName, $namespaceIndex );
		if ( $title->exists() ) {
			$out->addWikiMsg( 'createpage-titleexists', $title->getFullText() );
			$out->addHTML( Xml::tags( 'a', [
				'href' => $this->getEditURL( $title )
			], $out->msg( 'createpage-editexisting' )->escaped() ) );

			$out->addHTML( Xml::element( 'br' ) );
			$out->addHTML( Linker::linkKnown(
				$this->getPageTitle(),
				$out->msg( 'createpage-tryagain' )->escaped()
			) );

			return Status::newGood();
		}

		$out->redirect( $this->getEditURL( $title ) );
		return Status::newGood();
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}
}
