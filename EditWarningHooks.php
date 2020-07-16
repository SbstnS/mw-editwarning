<?php
namespace EditWarning;

use \EditWarning\EditWarning;
use \EditWarning\EditWarningMsg;
use OutputPage, Skin;

class EditWarningHooks {

	/**
	 * Setup EditWarning extension
	 *
	 * @return boolean Returns always true.
	 */
	public static function init() {
		if ( !defined( 'MEDIAWIKI' ) && !defined( 'EDITWARNING_UNITTEST' ) ) {
			echo <<<EOT
			<p>To install this extension, put the following line in LocalSettings.php:\n
			<pre>require_once "\$IP/extensions/EditWarning/EditWarning.php";</pre></p>\n\n
			
			<p>See <a href="http://www.mediawiki.org/wiki/Extension:EditWarning/0.4">http://www.mediawiki.org/wiki/Extension:EditWarning/0.4</a> for more information.</p>
EOT;
			exit( 1 );
		}


		// Used for messages to indicate edit type.
		define( 'TYPE_ARTICLE', 1 );
		define( 'TYPE_ARTICLE_SECTIONCONFLICT', 2 );
		define( 'TYPE_SECTION', 3 );

		return true;
	}

	/**
	 * Gets the section id from GET or POST
	 *
	 * @return int Section id.
	 */
	public function getSection() {
		if ( defined( 'EDITWARNING_UNITTEST' ) ) {
			return $GLOBALS['unitGetSection'];
		}

		if ( isset( $_GET['section'] ) && !isset( $_POST['wpSection'] ) ) {
			return intval( $_GET['section'] );
		} else {
			if ( isset( $_POST['wpSection'] ) ) {
				return intval( $_POST['wpSection'] );
			} else {
				return 0;
			}
		}
	}

	/**
	 * Function to show info message about created or updated locks for sections
	 * or articles.
	 *
	 * @param int $msgtype Type of edit (article or section).
	 */
	private function showInfoMsg( $msgtype, $timestamp, $cancel_url ) {
		$type = ( $msgtype == TYPE_ARTICLE ) ? "ArticleNotice" : "SectionNotice";

		// Show info message with updated timestamp.
		$msg_params[] = date( "Y-m-d", $timestamp );
		$msg_params[] = date( "H:i", $timestamp );
		$msg = EditWarningMsg::getInstance( $type, $cancel_url, $msg_params );
		$msg->show( $msgtype );
		unset( $msg );
	}

	/**
	 * Function to show warning message about existing locks for sections or
	 * articles.
	 *
	 * @param <type> $lockobj EditWarningLock object.
	 */
	private function showWarningMsg( $msgtype, $lockobj, $cancel_url ) {
		switch ( $msgtype ) {
			case TYPE_ARTICLE:
				$type = "ArticleWarning";
				break;
			case TYPE_ARTICLE_SECTIONCONFLICT:
				$type = "ArticleSectionWarning";
				break;
			case TYPE_SECTION:
				$type = "SectionWarning";
				break;
		}

		// Calculate time to wait
		$difference = floatval( abs( time() - $lockobj->getTimestamp() ) );
		$time_to_wait = round( $difference / 60, 0 );


		// Parameters for message string
		if ( $msgtype == TYPE_ARTICLE || $msgtype == TYPE_SECTION ) {
			$msg_params[] = $lockobj->getUserName();
			$msg_params[] = date( "Y-m-d", $lockobj->getTimestamp() );
			$msg_params[] = date( "H:i", $lockobj->getTimestamp() );
		}

		$msg_params[] = $time_to_wait;

		// Use minutes or seconds string?
		if ( $time_to_wait > 1 || $difference > 60 ) {
			$msg_params[] = wfMessage( 'ew-minutes' )->text();
		} else {
			$msg_params[] = wfMessage( 'ew-seconds' )->text();
		}

		$msg = EditWarningMsg::getInstance( $type, $cancel_url, $msg_params );
		$msg->show($type);
		unset( $msg );
	}



	/**
	 * Action on article editing
	 *
	 * @hook BeforePageDisplay
	 * @param object $editpage Editpage object.
	 * @param object $ew EditWarning object
	 * @return boolean|int It returns a constant int if it runs in unit test
	 *                     environment, else true.
	 */

	public static function edit( OutputPage $out, Skin $skin ) {
		global $wgScriptPath, $request, $wgEditWarning_OnlyEditor, $PHP_SELF;

		$dbr = null;
		$dbw = null;

		$user = $out->getUser();

		$hook = new EditWarningHooks();
		$ew = new EditWarning();

		$request = $out->getRequest();
		// Add CSS styles to header
		if ( ( $request->getVal( 'action' ) == 'edit' || $request->getVal( 'action' ) == 'formedit' || $request->getVal( 'veaction' ) == 'edit' || $request->getVal( 'action' ) == 'submit' ) &&
			$wgEditWarning_OnlyEditor &&
			$user->getID()
			>= 1	) {
			$out->addHeadItem( 'edit_css', '  <link href="' . $wgScriptPath . '/extensions/EditWarning/resources/css/article_edit.css" rel="stylesheet" type="text/css" />' );
		}

		$out->addModules(['ext.editwarning']);

		if ( $_REQUEST['action'] === 'edit' || $_REQUEST['action'] === 'formedit' || $_REQUEST['veaction'] === 'edit' ) {

			// Abort on nonexisting pages
			if ( $out->getTitle()->getArticleID() < 1 ) {
				return true;
			}

			if ( !defined( 'EDITWARNING_UNITTEST' ) ) {
				$dbr = wfGetDB( DB_SLAVE );
				$dbw = wfGetDB( DB_MASTER );
			}

			$ew->setUserID( $user->getID() );
			$ew->setUserName( $user->getName() );
			$ew->setArticleID( $out->getTitle()->getArticleID() );
			$section = $hook->getSection();
			$msg_params = array();

			// Get article title for cancel button
			if ( $out->getTitle()->getNamespace() == 'NS_MAIN' ) {
				$article_title = $out->getTitle()->getPartialURL();
			} else {
				$article_title = $article_title = $out->getTitle()->getNsText() . ":" . $article_title = $out->getTitle()->getPartialURL();
			}

			$url = $PHP_SELF . "?title=" . $article_title . "&cancel=true";

			// Check request values
			if ( $section > 0 ) {
				// Section editing
				$ew->setSection( $section );
				$ew->load( $dbr );

				// Is the whole article locked?
				if ( $ew->isArticleLocked() ) {
					// Is it by the user?
					if ( $ew->isArticleLockedByUser() ) {
						// The user has already a lock on the whole article, but
						// edits now a single section. Change article lock to
						// section lock.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_SECTION_NEW;
						}

						$ew->removeLock( $dbw );
						$ew->saveLock( $dbw, $section );
						$hook->showInfoMsg( TYPE_SECTION, $ew->getTimestamp( TIMESTAMP_NEW ), $url );
						unset( $ew );

						return true;
					} else {
						// Someone else has a lock on the whole article. Show warning.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_ARTICLE_OTHER;
						}

						$hook->showWarningMsg( TYPE_ARTICLE, $ew->getArticleLock(), $url );
						unset( $ew );

						return true;
					}
				} elseif ( $ew->isSectionLocked( $section ) ) {
					$sectionLock = $ew->getSectionLock( $section );

					// Is the section locked by the user?
					if ( $ew->isSectionLockedByUser( $sectionLock ) ) {
						// The section is locked by the user. Update lock.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_SECTION_USER;
						}

						$ew->updateLock( $dbw, $section );
						$hook->showInfoMsg( TYPE_SECTION, $ew->getTimestamp( TIMESTAMP_NEW ), $url );
						unset( $ew );

						return true;
					} else {
						// The section is locked by someone else. Show warning.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_SECTION_OTHER;
						}

						$hook->showWarningMsg( TYPE_SECTION, $sectionLock, $url );
						unset( $ew );

						return true;
					}
				} else {
					// No locks. Create section lock for user.
					if ( defined( 'EDITWARNING_UNITTEST' ) ) {
						return EDIT_SECTION_NEW;
					}

					// Don't save locks for anonymous users.
					if ( $ew->getUserID() < 1 ) {
						return true;
					}

					$ew->saveLock( $dbw, $section );
					$hook->showInfoMsg( TYPE_SECTION, $ew->getTimestamp( TIMESTAMP_NEW ), $url );
					unset( $ew );

					return true;
				}
			} else {
				// Article editing
				$ew->load( $dbr );

				// Is the article locked?
				if ( $ew->isArticleLocked() ) {
					if ( $ew->isArticleLockedByUser() ) {
						// The article is locked by the user. Update lock.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_ARTICLE_USER;
						}

						$ew->updateLock( $dbw );
						$hook->showInfoMsg( TYPE_ARTICLE, $ew->getTimestamp( TIMESTAMP_NEW ), $url );
						unset( $ew );

						return true;
					} else {
						// The article is locked by someone else. Show warning.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_ARTICLE_OTHER;
						}

						$article_lock = $ew->getArticleLock();
						$hook->showWarningMsg( TYPE_ARTICLE, $article_lock, $url );
						unset( $ew );

						return true;
					}
				} elseif ( $ew->anySectionLocks() ) {
					// There is at least one section lock
					if ( $ew->anySectionLocksByOthers() ) {
						// At least one section lock by another user.
						// So an article lock is not possible. Show warning.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_SECTION_OTHER;
						}

						$sectionLocks = $ew->getSectionLocksByOther();
						// Get the newest lock of a section for the warning message.
						$lock = $sectionLocks[$ew->getSectionLocksByOtherCount() - 1];
						$hook->showWarningMsg( TYPE_ARTICLE_SECTIONCONFLICT, $lock, $url );
						unset( $ew );

						return true;
					} else {
						// The user has exclusively one or more locks on sections
						// of the article, but now wants to edit the whole article.
						// Change sections locks to article lock.
						if ( defined( 'EDITWARNING_UNITTEST' ) ) {
							return EDIT_ARTICLE_NEW;
						}

						$ew->removeUserLocks( $dbw );
						$ew->saveLock( $dbw );
						$hook->showInfoMsg( TYPE_ARTICLE, $ew->getTimestamp( TIMESTAMP_NEW ), $url );
						unset( $ew );

						return true;
					}
				} else {
					// No locks. Create new article lock.
					if ( defined( 'EDITWARNING_UNITTEST' ) ) {
						return EDIT_ARTICLE_NEW;
					}

					// Don't save locks for anonymous users.
					if ( $ew->getUserID() < 1 ) {
						return true;
					}

					$ew->saveLock( $dbw );
					$hook->showInfoMsg( TYPE_ARTICLE, $ew->getTimestamp( TIMESTAMP_NEW ), $url );
					unset( $ew );
				}
			}
		} else {
			// Action if saved or aborted.
			// !!! This actions is called on each page load except edit actions
			if($out->getTitle()->getNamespace() > -1){
				$hook->removeWarning( $ew, $out->getWikiPage(), $user );
			}
		}

		return true;
	}

	/**
	 * Action if article is saved / canceled.
	 *
	 * @param
	 * @return boolean Returns always true.
	 */

	private function removeWarning( $ew, $wikiPage, $user ) {
		// Abort on nonexisting pages or anonymous users.

		if ( $wikiPage->getTitle()->getArticleID() < 1 || $user->getID() < 1 ) {
			return true;
		}

		$dbw = wfGetDB( DB_MASTER );
		$ew->setUserID( $user->getID() );
		$ew->setArticleID( $wikiPage->getTitle()->getArticleID() );
		$ew->removeLock( $dbw );

		return true;
	}

	/**
	 * Action on user logout.
	 *
	 * @hook UserLogout
	 * @param user User object.
	 * @return boolean Returns always true.
	 *
	 */
	function logout( $user ) {
		$ew = new EditWarning();

		$dbw = wfGetDB( DB_MASTER );
		$ew->setUserID( $user->getID() );
		$ew->removeUserLocks( $dbw );

		return true;
	}
}