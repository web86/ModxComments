<?php
$_lang['modxcomments']='Комментарии';
$_lang['modxcomments.intro']='Просмотр, поиск и модерация комментариев. Для действий используйте правый клик или двойной клик по строке.';
$_lang['modxcomments.resource']='Ресурс';
$_lang['modxcomments.author']='Автор';
$_lang['modxcomments.email']='Email';
$_lang['modxcomments.comment']='Комментарий';
$_lang['modxcomments.status']='Статус';
$_lang['modxcomments.createdon']='Создан';
$_lang['modxcomments.search']='Поиск комментариев…';
$_lang['modxcomments.clear']='Сбросить';
$_lang['modxcomments.all']='Все';
$_lang['modxcomments.published']='Опубликованные';
$_lang['modxcomments.pending']='На модерации';
$_lang['modxcomments.spam']='Спам';
$_lang['modxcomments.publish']='Опубликовать';
$_lang['modxcomments.mark_pending']='Отправить на модерацию';
$_lang['modxcomments.mark_spam']='Пометить как спам';
$_lang['modxcomments.delete']='Удалить';
$_lang['modxcomments.delete_confirm']='Удалить комментарий? Ответы в ветке сохранятся.';
$_lang['modxcomments.status_updated']='Статус комментария обновлён.';
$_lang['modxcomments.deleted']='Комментарий удалён.';
$_lang['modxcomments.hard_delete']='Удалить полностью';
$_lang['modxcomments.hard_delete_confirm']='Удалить комментарий без возможности восстановления? Все ответы на него и связанные голоса тоже будут удалены.';
$_lang['modxcomments.hard_deleted']='Комментарий и его ответы полностью удалены.';
$_lang['modxcomments.actions']='Действия';

$_lang['area_modxcomments']='ModxComments';

$_lang['setting_modxcomments.allow_guests']='Разрешить комментарии гостей';
$_lang['setting_modxcomments.allow_guests_desc']='Если включено, неавторизованные посетители могут оставлять комментарии. Для гостя обязательны имя и email.';

$_lang['setting_modxcomments.max_depth']='Максимальная глубина ветки';
$_lang['setting_modxcomments.max_depth_desc']='Максимальная вложенность ответов. Корневой комментарий имеет глубину 0. По умолчанию: 5.';

$_lang['setting_modxcomments.max_length']='Максимальная длина комментария';
$_lang['setting_modxcomments.max_length_desc']='Максимальное количество символов в одном комментарии. По умолчанию: 5000.';

$_lang['setting_modxcomments.edit_time']='Время на редактирование/удаление';
$_lang['setting_modxcomments.edit_time_desc']='Количество секунд после публикации, в течение которых автор может изменить или удалить свой комментарий. Авторизованный пользователь определяется по user ID, гость — по безопасному ownership cookie/hash. 900 секунд = 15 минут.';

$_lang['setting_modxcomments.rate_limit_count']='Лимит комментариев';
$_lang['setting_modxcomments.rate_limit_count_desc']='Максимальное количество комментариев с одного хешированного IP за заданное окно времени. По умолчанию: 5.';

$_lang['setting_modxcomments.rate_limit_window']='Окно ограничения частоты';
$_lang['setting_modxcomments.rate_limit_window_desc']='Период ограничения частоты в секундах. По умолчанию: 60.';

$_lang['setting_modxcomments.guest_status']='Статус нового комментария гостя';
$_lang['setting_modxcomments.guest_status_desc']='Статус, который получает новый гостевой комментарий. "published" — публиковать сразу, "pending" — отправлять на модерацию.';

$_lang['setting_modxcomments.user_status']='Статус комментария авторизованного пользователя';
$_lang['setting_modxcomments.user_status_desc']='Статус нового комментария авторизованного MODX web-пользователя. Используйте "published" или "pending".';

$_lang['setting_modxcomments.captcha_enabled']='Включить CAPTCHA';
$_lang['setting_modxcomments.captcha_enabled_desc']='Включает защиту формы комментариев выбранным CAPTCHA-провайдером.';
$_lang['setting_modxcomments.captcha_provider']='CAPTCHA-провайдер';
$_lang['setting_modxcomments.captcha_provider_desc']='Провайдер CAPTCHA: none, turnstile, hcaptcha, recaptcha или yandex.';
$_lang['setting_modxcomments.captcha_guests_only']='CAPTCHA только для гостей';
$_lang['setting_modxcomments.captcha_guests_only_desc']='Если включено, CAPTCHA требуется только неавторизованным посетителям.';

$_lang['setting_modxcomments.hcaptcha_site_key']='hCaptcha site key';
$_lang['setting_modxcomments.hcaptcha_site_key_desc']='Публичный site key hCaptcha.';
$_lang['setting_modxcomments.hcaptcha_secret_key']='hCaptcha secret key';
$_lang['setting_modxcomments.hcaptcha_secret_key_desc']='Секретный ключ hCaptcha для серверной проверки.';

$_lang['setting_modxcomments.recaptcha_version']='Google reCAPTCHA версия';
$_lang['setting_modxcomments.recaptcha_version_desc']='Используйте v2 для видимого checkbox-виджета или v3 для невидимой score-проверки.';
$_lang['setting_modxcomments.recaptcha_site_key']='Google reCAPTCHA site key';
$_lang['setting_modxcomments.recaptcha_site_key_desc']='Публичный site key Google reCAPTCHA.';
$_lang['setting_modxcomments.recaptcha_secret_key']='Google reCAPTCHA secret key';
$_lang['setting_modxcomments.recaptcha_secret_key_desc']='Секретный ключ Google reCAPTCHA для серверной проверки.';
$_lang['setting_modxcomments.recaptcha_min_score']='Минимальный score reCAPTCHA v3';
$_lang['setting_modxcomments.recaptcha_min_score_desc']='Минимальный допустимый score для reCAPTCHA v3 от 0 до 1. По умолчанию: 0.5.';

$_lang['setting_modxcomments.yandex_client_key']='Yandex SmartCaptcha client key';
$_lang['setting_modxcomments.yandex_client_key_desc']='Ключ клиентской части Yandex SmartCaptcha.';
$_lang['setting_modxcomments.yandex_server_key']='Yandex SmartCaptcha server key';
$_lang['setting_modxcomments.yandex_server_key_desc']='Серверный ключ Yandex SmartCaptcha для проверки токена.';

$_lang['setting_modxcomments.turnstile_enabled']='Включить Cloudflare Turnstile';
$_lang['setting_modxcomments.turnstile_enabled_desc']='Включает проверку Cloudflare Turnstile при отправке комментариев. Также необходимо указать site key и secret key.';

$_lang['setting_modxcomments.turnstile_site_key']='Turnstile site key';
$_lang['setting_modxcomments.turnstile_site_key_desc']='Публичный ключ Cloudflare Turnstile, который используется frontend-виджетом.';

$_lang['setting_modxcomments.turnstile_secret_key']='Turnstile secret key';
$_lang['setting_modxcomments.turnstile_secret_key_desc']='Секретный ключ Cloudflare Turnstile для серверной проверки. Не должен попадать во frontend.';

$_lang['setting_modxcomments.turnstile_guests_only']='Turnstile только для гостей';
$_lang['setting_modxcomments.turnstile_guests_only_desc']='Если включено, Turnstile требуется только гостям. Авторизованные MODX web-пользователи отправляют комментарии без CAPTCHA.';


$_lang['setting_modxcomments.notify_admin']='Уведомлять администратора';
$_lang['setting_modxcomments.notify_admin_desc']='Если включено, отправляет email администратору о каждом новом комментарии, включая комментарии со статусом pending. Используются штатные настройки почты/SMTP MODX.';

$_lang['setting_modxcomments.notify_admin_email']='Email администратора для уведомлений';
$_lang['setting_modxcomments.notify_admin_email_desc']='Адрес, на который отправляются уведомления о новых комментариях. Если оставить пустым, используется системная настройка MODX "emailsender".';

$_lang['setting_modxcomments.notify_replies']='Уведомлять авторов об ответах';
$_lang['setting_modxcomments.notify_replies_desc']='Если включено, автор родительского комментария получает email, когда ответ становится опубликованным. Ответы pending не отправляются по почте до публикации модератором.';


$_lang['modxcomments.admin']='Админ';
$_lang['modxcomments.admin_reply']='Ответ администратора';
$_lang['modxcomments.reply_to']='Ответ на';
$_lang['modxcomments.thread_root']='Начало ветки';


$_lang['setting_modxcomments.threads_per_page']='Корневых веток на странице';
$_lang['setting_modxcomments.threads_per_page_desc']='Количество корневых веток комментариев на одной странице фронтенда. Ответы выбранных веток загружаются целиком, поэтому дерево ответов не разрезается между страницами. По умолчанию: 20.';

$_lang['setting_modxcomments.resource_signing_key']='Ключ подписи ресурсов';
$_lang['setting_modxcomments.resource_signing_key_desc']='Секретный HMAC-ключ, связывающий публичный API комментариев с ресурсами, где выведен сниппет ModxComments. Генерируется автоматически при установке. Не публикуйте и не меняйте без необходимости.';
