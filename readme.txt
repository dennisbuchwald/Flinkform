=== Flinkform - GDPR Contact Form Builder for the Block Editor ===
Contributors: dbwmediadennis
Tags: contact form, form builder, multi step form, kontaktformular, dsgvo
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.14.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Contact form builder for the Block Editor: multi-step forms, conditional logic and spam protection without CAPTCHA. Free and GDPR-friendly.

== Description ==

Flinkform is a free contact form builder for the WordPress Block Editor. You build contact forms, quote requests and multi-step forms right where you edit your pages: every field is a native block, and the form takes its colours, fonts and spacing from your theme.

Multi-step forms, conditional logic, a submissions dashboard and spam protection without CAPTCHA are all included in the free plugin. There is no reCAPTCHA and no other third-party service, so no visitor data leaves your server. That makes a GDPR-compliant contact form a lot easier to run.

**Try it before you install it:** live demo forms, including a multi-step form and conditional logic, at https://demo.flinkform.de/

= Contact form in two minutes =

Insert the Form block, add the fields you need and publish. Name, email and message are there in a few clicks, and the form looks like part of your site from the start, without extra CSS.

* 14 field types, including email, phone, date, dropdown, address and a dedicated consent checkbox
* Email notification to you, plus an optional confirmation email to the visitor
* Submissions are stored in WordPress, with search, filters and read state
* Success message or redirect to your own thank-you page

= Multi-step forms and conditional logic, free =

Split a long form into steps with the Page Break block. Visitors see a progress bar and every step is validated before they move on.

Conditional logic shows or hides fields, skips whole steps and can hold back the submit button until the right answers are given. Rules can be grouped, so "(A or B) and C" is possible.

= Spam protection without CAPTCHA =

No puzzle, no image grid, no checkbox to tick. Flinkform stops spam bots with a hidden honeypot field, a signed timing check and a small proof-of-work task that the browser solves in the background. Visitors without JavaScript get a simple maths question instead. It works out of the box, needs no API key and contacts no outside service.

= GDPR-friendly by design =

No IP logging, no user-agent logging, no tracking cookies and no external services in the free plugin. It includes a consent field with a link to your privacy policy, retention periods with automatic deletion, and support for the WordPress tools to export and erase personal data.

= Accessible =

Real labels, grouped choices, errors that screen readers announce and focus that moves to the first problem. The form works without JavaScript, and its markup passes axe-core checks against WCAG 2.1 A/AA with zero violations, including the error state.

= Flinkform Pro =

The optional Pro add-on adds Stripe payments (card, SEPA direct debit, Apple Pay, Google Pay, whichever methods you enable in Stripe), calculation fields, multi-file upload, SMTP delivery, webhooks, newsletter integrations, CSV export and custom CSS. Details and pricing: https://flinkform.de/pro

= Features (free plugin) =

**Form building**

* 14 field types: Text, Email, Textarea, Number, Date, URL, Phone, Select, Radio, Checkbox, Toggle, Hidden, Consent, Address
* Address field with street, postal code and city in a compact grid, with optional address line 2 and country
* Consent field for the privacy policy agreement
* Notice block for highlighted notes between fields (info, success, warning, important), which can appear only when a condition applies
* Section Heading and Page Break blocks for structuring longer forms
* Multi-step forms with per-step validation and a progress indicator (bar, dots or numbers)
* Conditional logic: show or hide fields, skip steps, gate the submit button, with nestable rule groups
* Two-column layout with a full-width option per field
* Radio, checkbox group and dropdown can be switched into each other without losing options or rules

**Styling**

* Colours, typography, spacing and border radius come from your theme's theme.json
* Style panel: primary colour, field style (bordered, soft, underline, minimal), label position (above, beside, floating, placeholder), submit button style (fill, outline, ghost) and colours for labels and help texts

**Notifications**

* Notification email on every submission, with configurable recipient and merge tags
* Optional confirmation email to the visitor
* Sender name and address per form, and a Reply-To for each email, without an SMTP plugin
* Sent through your site's standard WordPress mail (wp_mail)

**Spam protection**

* Honeypot and signed time check, always on, no configuration
* Proof-of-work challenge with an accessible maths fallback for visitors without JavaScript
* No CAPTCHA, no external service, no API key, no tracking cookies

**After submission**

* Success message or redirect to a thank-you page on your site (protected against open redirects)
* Optional submission ID and form ID as URL parameters for conversion tracking (GA4, Meta Pixel, Plausible and others)

**Admin**

* Submissions list with search, filter by form, sorting and bulk actions
* Detail view with all field labels and values
* Mark as read or unread
* Retention period per form with automatic daily deletion

= Under the hood =

Forms are made of native blocks (`block.json` v3) and run on the WordPress Interactivity API. No separate form builder screen, no shortcodes, no jQuery, and under 15 KB of frontend JavaScript (gzipped). Requires WordPress 6.5 or newer and PHP 8.1 or newer.

== Installation ==

1. In your WordPress admin, go to **Plugins > Add New Plugin** and search for "Flinkform", or upload the `flinkform` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Open any page or post in the Block Editor
4. Insert the **Form** block (search for "Flinkform" or "Form")
5. Add fields, adjust the settings in the block sidebar and publish

== Frequently Asked Questions ==

= How do I create a contact form? =

Open a page in the Block Editor and insert the **Form** block. Add fields such as name, email and message from the "Add field" button, set the notification recipient in the block sidebar and publish the page. Submissions arrive by email and are also stored under **Flinkform > Submissions** in your WordPress admin.

= How do I create a multi-step form? =

Insert a **Page Break** block between fields to split the form into steps, then choose a progress indicator style (bar, dots or numbers). Each step is validated before the visitor can continue, and steps can be skipped based on earlier answers.

= Does it work without reCAPTCHA? =

Yes. Flinkform has no reCAPTCHA integration at all and needs none. Spam is filtered by a honeypot field, a signed timing check and a proof-of-work challenge that falls back to a simple maths question when JavaScript is unavailable. No third-party service is contacted, so no visitor data leaves your server.

= Is it GDPR compliant? =

Flinkform stores submissions in your own WordPress database and contacts no external service in the free plugin. It logs no IP addresses and no user-agent strings, ships a consent field, supports retention periods with automatic deletion and works with the WordPress tools to export and erase personal data (see the Privacy section below for details). Whether your overall setup is compliant still depends on your privacy policy and your mail provider.

= How does conditional logic work? =

Select a field, block or step and add a rule in the block sidebar, for example "show this field when the answer to question 2 is Yes". Rules can show or hide fields and notices, skip steps and hold back the submit button. Groups with their own AND/OR let you combine rules like "(A or B) and C".

= Are multi-step forms and conditional logic really free? =

Yes. Multi-step forms with progress indicator, per-step validation and conditional step skipping are part of the free plugin, and so is conditional logic.

= Is Flinkform free? =

Yes. Flinkform is licensed under the GPLv2 and completely free, including multi-step forms and conditional logic. Everything you need to build and run real forms is in the free plugin. Flinkform Pro is a separate, paid add-on.

= Can I try Flinkform before installing it? =

Yes. A live demo runs at https://demo.flinkform.de/ with several forms to fill in and send, including a multi-step form and conditional logic. It is a real WordPress site running this plugin, no sign-up needed.

= Where are form submissions stored? =

In a table in your own WordPress database. You find them under **Flinkform > Submissions** with search, a filter by form and a read state. You can delete them one by one or let a retention period per form delete old ones automatically.

= Do I need a page builder like Elementor or Divi? =

No. Forms are built in the standard WordPress Block Editor from native blocks. No page builder, no shortcodes, no separate form builder screen.

= Can I switch from Contact Form 7 or WPForms? =

Yes, but forms need to be rebuilt in the Block Editor, there is no automatic importer. Rebuilding a typical contact form takes a few minutes because the fields are plain blocks.

= Is Flinkform available in German? =

Yes. The block editor, the admin screens and all texts visitors see are translated into German. Flinkform is developed in Heilbronn, Germany, and support is available in German and English.

= Does Flinkform work with my theme? =

Yes. Flinkform reads your theme's design tokens from `theme.json` and inherits colours, typography, spacing and border radius automatically. It is tested with GeneratePress, Twenty Twenty-Five, Astra and Kadence.

= How does the spam protection work? =

Flinkform uses three layers that need no setup:

1. **Honeypot:** a hidden field that bots fill in but humans never see
2. **Signed time check:** submissions sent faster than a person could fill in the form are rejected, and the timestamp is cryptographically signed so bots cannot forge it
3. **Proof-of-work challenge:** the visitor's browser solves a small computational task in the background, and visitors without JavaScript get a simple maths question instead

No external service is contacted, no tracking cookies are set and no personal data is shared.

= Is Flinkform accessible? =

Accessibility is built in: real label/for pairs, fieldset/legend for groups, errors announced via role="alert" and linked to their fields, focus management and aria-live announcements across multi-step navigation, visible focus rings, prefers-reduced-motion support and spam protection without a CAPTCHA. The rendered form markup passes automated axe-core checks against WCAG 2.1 A/AA with zero violations, including the validation error state. A formal audit with screen reader protocols has not been commissioned yet. Colours you choose in the editor and your theme's palette affect contrast and remain your responsibility.

= My notification emails don't arrive. What can I do? =

Email delivery depends on your host, and many hosts send `wp_mail()` unreliably. If notifications don't arrive, install an SMTP plugin that sends mail through a proper provider. It will handle delivery for Flinkform too.

= Can I redirect to a thank-you page after submission? =

Yes. In the block sidebar's "After Submit" panel, choose "Redirect to URL" and enter your thank-you page. Optionally append the submission ID and form ID as URL parameters for conversion tracking.

= Which WordPress and PHP versions do I need? =

WordPress 6.5 or higher and PHP 8.1 or higher. Flinkform uses modern WordPress APIs (Interactivity API, block.json v3, viewScriptModule) that older versions do not have.

== Screenshots ==

1. A contact form built from native blocks in the WordPress Block Editor
2. Multi-step form with progress bar and per-step validation
3. Conditional logic: show, hide or skip steps based on earlier answers
4. Submissions dashboard in WordPress with search, filters and read state
5. Spam protection without CAPTCHA: nothing for visitors to solve
6. The form takes colours and fonts from your theme's theme.json

== Changelog ==

= 1.14.3 =
* Fix (important): a submission sent very quickly after the first click into the form was dropped silently and the visitor landed on the homepage. Since 1.14.0 the anti-bot timer starts at the first contact with the form, so clicking into a field, picking an autofill entry and pressing Send within two seconds looked like a bot. The browser now waits out the remaining moment before sending, and should a submission still arrive too early, the visitor gets their filled-in form back with a request to send again.
* Fix (important): after a successful submission, going back, changing the message and sending again showed the success message without storing the second message. A resend with different content is now always treated as a new submission.
* Fix: logged-in visitors who kept a form open for more than about 20 minutes could get a "security check failed" page on submit. The automatic token renewal now runs as the logged-in user.
* Fix: forms on drafts, private pages and old revisions no longer accept submissions from visitors who cannot see those pages. Previewing a draft as its author still works.
* Fix: moving a form to a different page or into a template part could leave submissions pointing at the old place for a few minutes. The form index now notices removals and rebuilds itself once when a form cannot be found.
* Fix: the personal-data exporter and eraser (Tools → Export/Erase Personal Data) could stop early and miss submissions of a person who had sent more than 50.
* Fix: conditional rules now compare text the same way in the browser and on the server, including umlauts and trailing spaces from autofill.
* Fix: the notification email replies to the visitor's email address by default, also for forms that were never opened in the editor.
* Fix: German translation of the reply hint in the notification email no longer assumes the sender is a woman.
* Improvement: a field's error message disappears as soon as the field is corrected, the browser's duplicate error tooltip is gone, and error messages of two forms on the same page no longer get mixed up for screen readers.
* Improvement: on phones, form fields use at least 16px text, so iOS no longer zooms into the page when a field is tapped.
* Developer: new filter `flinkform_values_before_visibility` for values the server derives before conditional logic runs (used by Flinkform Pro's calculation field).

= 1.14.2 =
* Fix (important): a submission could still be lost in one specific case introduced by 1.14.0. Leave a filled-in form in a background tab for twenty minutes and come back: the automatic token renewal also renewed the signed timestamp, and a submit within the next two seconds — exactly what returning to a finished form looks like — was treated as a bot and dropped silently. The timestamp is now written once, when the visitor reaches the form, and never moved again. The token keeps renewing as before.
* Fix: the German translation now covers the messages added in 1.14.0. On German sites, the "please send again" message and the no-JavaScript route showed English text. The new Site Health check is translated too.

= 1.14.1 =
* Fix: restores the plugin name, author and description in the plugin header. 1.14.0 shipped with a shortened name and a changed author by mistake, which is what your Plugins screen showed. No functional change.

= 1.14.0 =
* Performance: pages with a form can be cached again. Until now every page holding a Flinkform form told caching plugins not to cache it, because the form carried a spam token, a security nonce and a signed render time that are only valid for one request. On a site measured for this release that cost about 0.7 seconds of extra server time on every view of a form page - and those are usually the pages that matter most. Flinkform now loads those values in the background the moment a visitor first touches the form, so the page itself is plain, cacheable HTML.
* Spam protection is unchanged in strength. The same proof-of-work, the same signed single-use token, the same honeypot. The minimum-fill-time check actually gets more accurate: it now starts counting when the visitor reaches the form instead of when the page was rendered.
* Without JavaScript the form still works. Those visitors are offered a one-click link to an uncached version of the same page, which behaves exactly as before with the arithmetic question.
* Submissions that arrive before the background load finished are no longer lost. Everything typed is kept and the form comes back asking to send it again, instead of the request being dropped.
* New: Tools > Site Health reports whether your form pages are actually being cached, so this cannot quietly break again.
* Forms with a payment field (Pro) keep the previous behaviour and stay uncached, because the payment field brings request-specific data of its own.
* For developers: the old behaviour is one filter away - `add_filter( 'flinkform_render_challenge_inline', '__return_true' );`.

= 1.13.3 =
* Fix: with the "Floating" label position, the resting label sat at the bottom edge of the field or slipped below it instead of sitting in the middle. It showed up wherever a field wrapper was taller than the input itself - a field with help text underneath, or a two-column row stretched to match a taller neighbour - because the label was centred on the whole field, not on the input. It is now anchored to the input, whatever else the field carries. The lifted state, the notch on the border, textareas, selects and date fields are unchanged.

= 1.13.2 =
* Fix: the Style panel cut its own option labels short. "Bordered / Soft / Underline / Minimal" and "Above / Beside / Floating / Hidden" were squeezed into a ~250px sidebar as segmented buttons and came out as "Borde… / Unde… / Minim…", so the setting could only be guessed at. Every setting with more than a couple of options is a dropdown now and reads in full, in every language. Nothing changes on the front end: the same values, the same defaults, existing forms render exactly as before.
* Reported by Eric Saner — thank you!

= 1.13.1 =
* Fix: closes a tiny timing gap in the 1.13.0 token refresh. For a fraction of a second right after the token renewed itself, the form briefly held a fresh token with no solution yet, and a submit landing in that window could still be dropped. The refresh now solves the new challenge before swapping it in, so the token and its solution are always written together and that window no longer exists.

= 1.13.0 =
* Fix (important): a submission could be lost without a trace. The anti-spam token is valid for 30 minutes; if a form sat open longer than that (a long multi-step form, a tab left open, an HTML cache serving an older page), submitting sent the visitor silently to the home page with no message and no email, and their entries were gone. A form that came from our own server is never a bot, so an expired or already-used token no longer drops the submission: the form re-renders with the entries kept and a clear "your session has expired, please send again" message, and the second attempt goes through. The no-JavaScript path is covered too.
* New: the token now refreshes itself in the browser before it can expire, so the situation above rarely arises in the first place. A small, cache-safe endpoint mints a fresh challenge; the form renews roughly ten minutes before expiry, when a tab regains focus, and when restored from the back/forward cache. Forms in a popup repair an expired token automatically and resend once, without the visitor retyping anything. The page itself may now be cached without breaking submissions.
* Fix: a double-click or a back-button resend no longer lands on the home page. The first submission is saved once, and a repeat shows the success page instead of being rejected.

= Earlier versions =

The complete release history is in `changelog.txt`, shipped with the plugin and available at https://github.com/dennisbuchwald/Flinkform/blob/main/changelog.txt

== Upgrade Notice ==

= 1.14.3 =
Important: fixes two cases where a submission could be lost (sending within two seconds of the first click, and resending a changed message from the back button). Recommended for everyone on 1.14.x.

= 1.14.2 =
Important: closes a case where a submission from a long-open tab could still be dropped silently, introduced in 1.14.0. Update if you are on 1.14.0 or 1.14.1.

= 1.14.1 =
Restores the plugin name and author in the plugin header, which 1.14.0 changed by mistake. No functional change.

= 1.14.0 =
Pages with a form can be cached again - typically a few hundred milliseconds faster per view, with unchanged spam protection. Clear your page cache after updating.

= 1.13.3 =
Style fix for floating labels: the resting label is centred on the input again, also next to help text and in two-column layouts.

= 1.13.2 =
Editor fix: the Style panel no longer truncates its option labels. Front end unchanged.

= 1.13.1 =
Closes a small timing gap in the 1.13.0 token refresh. Recommended follow-up to 1.13.0.

= 1.13.0 =
Important: submissions could be lost silently when a form sat open past the 30-minute spam-token window. Fixed - no submission is dropped without a message, and the token now refreshes itself. Update recommended for every site.

== Privacy ==

Flinkform is built with privacy by default. Here is what the free plugin does and does not do:

**What the free plugin stores:**

* Form submissions (the field values visitors enter) in a dedicated database table (`{prefix}flinkform_submissions`)

**What the free plugin does NOT do:**

* It stores no IP addresses and no browser user-agent strings
* It sets no tracking, analytics or marketing cookies. Flinkform sets exactly one strictly-necessary cookie, `flinkform_flash` (lifetime about 60 seconds, httpOnly), and only when a form submission fails validation, to carry the error message and the visitor's input across the page reload. Successful submissions set no cookie at all
* It contacts no external service

**Data retention:**

* By default, submissions are retained until you delete them. To comply with the storage-limitation principle (GDPR Art. 5), set a per-form retention period (Form block → Data Retention) and Flinkform deletes older submissions automatically each day
* Individual submissions can be deleted from the admin submissions screen at any time

**Data deletion:**

* All data of the free plugin (the submissions table) is permanently removed when the plugin is uninstalled through the WordPress admin
* Flinkform integrates with WordPress's privacy tools (Tools > Export Personal Data / Erase Personal Data) to support data-subject access and erasure requests

== Source Code ==

The complete, uncompiled source code (including the `src/` directory with the
unminified JavaScript/CSS that compiles into `build/`) is publicly available at:
https://github.com/dennisbuchwald/Flinkform

Build instructions (Node.js 18+ and npm required):

1. Clone the repository: `git clone https://github.com/dennisbuchwald/Flinkform.git`
2. Install dependencies: `npm install`
3. Build the compiled assets into `build/`: `npm run build`

The build is powered by `@wordpress/scripts` (webpack). The `src/` sources are
excluded from the distributed plugin zip to keep it small; this repository is
the canonical, reviewable source.
