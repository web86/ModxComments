<?php
$_lang['modxcomments']='Comments';
$_lang['modxcomments.intro']='Review, search and moderate comments from one place. Right-click or double-click a row for actions.';
$_lang['modxcomments.resource']='Resource';
$_lang['modxcomments.author']='Author';
$_lang['modxcomments.email']='Email';
$_lang['modxcomments.comment']='Comment';
$_lang['modxcomments.status']='Status';
$_lang['modxcomments.createdon']='Created';
$_lang['modxcomments.search']='Search comments…';
$_lang['modxcomments.clear']='Clear';
$_lang['modxcomments.all']='All';
$_lang['modxcomments.published']='Published';
$_lang['modxcomments.pending']='Pending';
$_lang['modxcomments.spam']='Spam';
$_lang['modxcomments.publish']='Publish';
$_lang['modxcomments.mark_pending']='Mark pending';
$_lang['modxcomments.mark_spam']='Mark spam';
$_lang['modxcomments.delete']='Delete';
$_lang['modxcomments.delete_confirm']='Soft-delete this comment? Replies will remain.';
$_lang['modxcomments.status_updated']='Comment status updated.';
$_lang['modxcomments.deleted']='Comment deleted.';
$_lang['modxcomments.hard_delete']='Delete permanently';
$_lang['modxcomments.hard_delete_confirm']='Permanently delete this comment? All replies below it and related votes will also be removed.';
$_lang['modxcomments.hard_deleted']='Comment and its replies permanently deleted.';
$_lang['modxcomments.actions']='Actions';

$_lang['area_modxcomments']='ModxComments';

$_lang['setting_modxcomments.allow_guests']='Allow guest comments';
$_lang['setting_modxcomments.allow_guests_desc']='If enabled, visitors who are not authenticated as MODX web users may submit comments. Guest name and email are required.';

$_lang['setting_modxcomments.max_depth']='Maximum reply depth';
$_lang['setting_modxcomments.max_depth_desc']='Maximum nesting level for comment replies. Root comments have depth 0. Default: 5.';

$_lang['setting_modxcomments.max_length']='Maximum comment length';
$_lang['setting_modxcomments.max_length_desc']='Maximum number of characters allowed in a comment. Default: 5000.';

$_lang['setting_modxcomments.edit_time']='Edit/delete window';
$_lang['setting_modxcomments.edit_time_desc']='Number of seconds after posting during which an author may edit or delete their own comment. Authenticated users are matched by user ID; guests are matched by a secure ownership cookie/hash. 900 seconds = 15 minutes.';

$_lang['setting_modxcomments.rate_limit_count']='Rate-limit comment count';
$_lang['setting_modxcomments.rate_limit_count_desc']='Maximum number of comments accepted from the same hashed IP during the rate-limit window. Default: 5.';

$_lang['setting_modxcomments.rate_limit_window']='Rate-limit window';
$_lang['setting_modxcomments.rate_limit_window_desc']='Rate-limit time window in seconds. Default: 60.';

$_lang['setting_modxcomments.guest_status']='Default guest comment status';
$_lang['setting_modxcomments.guest_status_desc']='Status assigned to newly submitted guest comments. Use "published" for immediate publication or "pending" for moderation.';

$_lang['setting_modxcomments.user_status']='Default authenticated-user comment status';
$_lang['setting_modxcomments.user_status_desc']='Status assigned to new comments submitted by authenticated MODX web users. Use "published" or "pending".';

$_lang['setting_modxcomments.captcha_enabled']='Enable CAPTCHA';
$_lang['setting_modxcomments.captcha_enabled_desc']='Protect the comment form with the selected CAPTCHA provider.';
$_lang['setting_modxcomments.captcha_provider']='CAPTCHA provider';
$_lang['setting_modxcomments.captcha_provider_desc']='CAPTCHA provider: none, turnstile, hcaptcha, recaptcha, or yandex.';
$_lang['setting_modxcomments.captcha_guests_only']='CAPTCHA for guests only';
$_lang['setting_modxcomments.captcha_guests_only_desc']='If enabled, CAPTCHA is required only for unauthenticated visitors.';

$_lang['setting_modxcomments.hcaptcha_site_key']='hCaptcha site key';
$_lang['setting_modxcomments.hcaptcha_site_key_desc']='Public hCaptcha site key.';
$_lang['setting_modxcomments.hcaptcha_secret_key']='hCaptcha secret key';
$_lang['setting_modxcomments.hcaptcha_secret_key_desc']='Private hCaptcha secret used for server-side verification.';

$_lang['setting_modxcomments.recaptcha_version']='Google reCAPTCHA version';
$_lang['setting_modxcomments.recaptcha_version_desc']='Use v2 for the visible checkbox widget or v3 for invisible score-based verification.';
$_lang['setting_modxcomments.recaptcha_site_key']='Google reCAPTCHA site key';
$_lang['setting_modxcomments.recaptcha_site_key_desc']='Public Google reCAPTCHA site key.';
$_lang['setting_modxcomments.recaptcha_secret_key']='Google reCAPTCHA secret key';
$_lang['setting_modxcomments.recaptcha_secret_key_desc']='Private Google reCAPTCHA secret used for server-side verification.';
$_lang['setting_modxcomments.recaptcha_min_score']='reCAPTCHA v3 minimum score';
$_lang['setting_modxcomments.recaptcha_min_score_desc']='Minimum accepted reCAPTCHA v3 score from 0 to 1. Default: 0.5.';

$_lang['setting_modxcomments.yandex_client_key']='Yandex SmartCaptcha client key';
$_lang['setting_modxcomments.yandex_client_key_desc']='Client-side key used to render Yandex SmartCaptcha.';
$_lang['setting_modxcomments.yandex_server_key']='Yandex SmartCaptcha server key';
$_lang['setting_modxcomments.yandex_server_key_desc']='Server-side key used to verify Yandex SmartCaptcha tokens.';

$_lang['setting_modxcomments.turnstile_enabled']='Enable Cloudflare Turnstile';
$_lang['setting_modxcomments.turnstile_enabled_desc']='Enable Cloudflare Turnstile verification for comment submission. Site key and secret key must also be configured.';

$_lang['setting_modxcomments.turnstile_site_key']='Turnstile site key';
$_lang['setting_modxcomments.turnstile_site_key_desc']='Public Cloudflare Turnstile site key used by the frontend widget.';

$_lang['setting_modxcomments.turnstile_secret_key']='Turnstile secret key';
$_lang['setting_modxcomments.turnstile_secret_key_desc']='Private Cloudflare Turnstile secret used only for server-side verification. Do not expose this value publicly.';

$_lang['setting_modxcomments.turnstile_guests_only']='Turnstile for guests only';
$_lang['setting_modxcomments.turnstile_guests_only_desc']='If enabled, Turnstile is required only for guests. Authenticated MODX web users can submit without CAPTCHA.';


$_lang['setting_modxcomments.notify_admin']='Notify administrator';
$_lang['setting_modxcomments.notify_admin_desc']='If enabled, send an email notification for every newly submitted comment, including comments that are pending moderation. Uses the standard MODX mail/SMTP configuration.';

$_lang['setting_modxcomments.notify_admin_email']='Administrator notification email';
$_lang['setting_modxcomments.notify_admin_email_desc']='Email address that receives new-comment notifications. If empty, the MODX system setting "emailsender" is used.';

$_lang['setting_modxcomments.notify_replies']='Notify authors about replies';
$_lang['setting_modxcomments.notify_replies_desc']='If enabled, email the author of the parent comment when a reply becomes published. Pending replies are not emailed until a moderator publishes them.';


$_lang['modxcomments.admin']='Admin';
$_lang['modxcomments.admin_reply']='Administrator reply';
$_lang['modxcomments.reply_to']='Reply to';
$_lang['modxcomments.thread_root']='Thread root';


$_lang['setting_modxcomments.threads_per_page']='Root threads per page';
$_lang['setting_modxcomments.threads_per_page_desc']='Number of root comment threads shown on one frontend page. All visible replies belonging to those roots are loaded together, so a reply tree is never split between pages. Default: 20.';

$_lang['setting_modxcomments.resource_signing_key']='Resource signing key';
$_lang['setting_modxcomments.resource_signing_key_desc']='Private HMAC key used to bind the public comments API to resources where the ModxComments snippet is rendered. Generated automatically during installation. Do not expose or change it casually.';
