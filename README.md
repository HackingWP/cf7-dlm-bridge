Contact Form 7 Download Monitor Bridge
======================================

*Send access to members only downloads of Download monitor using Contact form 7 forms*

## Dependencies

1. [Download Monitor](https://github.com/attitude/download-monitor/tree/feature/fix_post_meta_json_escaping) to manage downloads
1. [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) to create custom forms
1. [WP Access Tokens API](https://github.com/HackingWP/wp-access-token-api) for requesting and validating tokens

## Known Issues

For now, I'm using my [modified version of Download Monitor](https://github.com/attitude/download-monitor/commit/e63bb094bb67ae1da7974f83425a999d7c554731).
Needs more testing against the latest.

## Installation

1. Download [zip](https://github.com/HackingWP/cf7-dlm-bridge/archive/master.zip) and
   upload to your `wp-content/plugins` or install via `wp-admin` Plugins interface;
1. Activate

## Docs

Plugin generates list of specified versions (or all if nothing is enclosed within
shortcode) of a download created using Download Monitor plugin.

By giving your download versions name you'll be able to use those names in the CF7 form
for multifile download using checkbox.

Example generated CF7 tag:

```
[checkbox checkbox-download-versions use_label_element "sunny version" "windy version" "snowy version"]
```

Used in the Mail or Mail(2) CF7 sections:

```
[cf7_dlm_download id="123" ttl="30" html="1"][checkbox-download-versions][/cf7_dlm_download]
```

Shortcode works in the `post_content` area too so you can use the password protected
posts too. You need to explicitly define which versions to insert, no format is enforced,
since comparison is quirky (for now):

```
[cf7_dlm_download id="123" ttl="30" html="1"]sunny version, windy version, snowy version[/cf7_dlm_download]
```

#### Shortcode options

- **id:** download id (required)
- **ttl:** time to live (how long token should last); default is 15
- **retries:** number of allowed uses of token; default is 1, which means strictly 1 download
- **html:** to generate `<li>` elements use `1`, for plaintext list using `-` use `0`

#### Defaults

To set your own defaults, use available filter hooks:

- DLM_CF7_Bridge_ttl
- DLM_CF7_Bridge_retries

For more insight please see inline docs

---

Enjoy!

[@martin_adamko](http://twitter.com/martin_adamko)
