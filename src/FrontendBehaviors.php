<?php

declare(strict_types=1);

namespace Dotclear\Plugin\FrontendSession;

use Dotclear\App;
use Dotclear\Database\{Cursor, MetaRecord };
use Exception;

/**
 * @brief       FrontendSession frontend behaviors.
 * @ingroup     FrontendSession
 *
 * @author      Jean-Christian Paul Denis
 * @copyright   AGPL-3.0
 */
class FrontendBehaviors
{
    /**
     * Overload posts record extension.
     */
    public static function coreBlogGetPosts(MetaRecord $rs): void
    {
        // Overload commentsActive()
        $rs->extend(RecordExtendPost::class);
    }

    /**
     * Overload comment creation.
     */
    public static function publicBeforeCommentCreate(Cursor $cur): void
    {
        // recheck if post comment is closed, should never happened
        $rs = $cur->getField('post_id') ? App::blog()->getPosts(['post_id' => $cur->getField('post_id')]) : null;
        if (!$rs instanceof MetaRecord || !$rs->f('post_open_comment')) {
            return;
        }

        $option = new CommentOptions($rs, $cur);

        # --BEHAVIOR-- FrontendSessionCommentsActive -- CommentOptions
        App::behavior()->callBehavior('FrontendSessionCommentsActive', $option);

        // check third party plugins
        if (is_bool($option->isModerate())) {
            $cur->setField('comment_status', $option->isModerate() ? App::status()->comment()::UNPUBLISHED : App::status()->comment()::PUBLISHED);
        }

        // recheck comment active, should never happened. if no option and limit and user not auth = stop
        if ($option->isActive() === null && My::settings()->get('limit_comment') && App::auth()->userID() == '') {
            throw new Exception(__('Comments creation are limited to registered users.'));
        }

        // Sorry not sorry, use user info if he is registered
        if (App::auth()->check(My::id(), App::blog()->id())) {
            $cur->setField('comment_author', App::auth()->getInfo('user_cn'));
            $cur->setField('comment_email', App::auth()->getInfo('user_email'));
            $cur->setField('comment_site', App::auth()->getInfo('user_url'));
        }
    }

    /**
     * Overload frontend CSS.
     */
    public static function publicHeadContent(): void
    {
        $tplset = App::themes()->moduleInfo(App::blog()->settings()->get('system')->get('theme'), 'tplset');

        // Load post creation page CSS
        if (!My::settings()->get('disable_css') && $tplset == 'dotty') {
            echo My::cssLoad('frontend-dotty');
        }

        // Hide comment form input. This does not work with all themes.
        if (App::auth()->check(My::id(), App::blog()->id())) {
            if ($tplset == 'mustek') {
                echo '<!-- FrontendSession special -->' . "\n" .
                    '<style>' .
                    '#comment-form .field:has(> #c_name), #comment-form .field:has(> #c_mail), #comment-form .field:has(> #c_site), #comment-form .remember {' .
                    'display:none;' .
                    '}' .
                    '</style>' . "\n";
            } else { // dotty
                echo '<!-- FrontendSession special -->' . "\n" .
                    '<style>' .
                    '#comment-form .name-field, #comment-form .mail-field, #comment-form .site-field, #comment-form .remember {' .
                    'display:none;' .
                    '}' .
                    '</style>' . "\n";
            }
        }
    }

    /**
     * Overload comment form field values.
     */
    public static function publicCommentFormBeforeContent(): void
    {
        // Comment form auto complete
        if (App::auth()->check(My::id(), App::blog()->id())
            && App::frontend()->context()->comment_preview['content'] == ''
        ) {
            App::frontend()->context()->comment_preview['name'] = App::auth()->getInfo('user_cn');
            App::frontend()->context()->comment_preview['mail'] = App::auth()->getInfo('user_email');
            App::frontend()->context()->comment_preview['site'] = App::auth()->getInfo('user_url');
        }
    }
}
