# Extending Real Treasury Gate

This guide explains safe extension points for adding capabilities without breaking plugin invariants.

## Extension principles

- Keep API namespace stable: `/wp-json/rtg/v1`.
- Do not store raw tokens.
- Keep `/validate` free of lead PII.
- Prefer additive changes (new event types, optional metadata, new UI sections).

## Common extension scenarios

## 1) Add a new asset type

Base model lives in:

- `rtg_assets.type`
- `rtg_assets.config`

Current core types: `download`, `video`, `link`.

To add a type (example: `audio`):

1. Update admin asset editor in `includes/class-admin.php` to allow `audio` in type selection.
2. Define config shape (for example `{ "audio_url": "..." }`) and sanitize on save.
3. Update frontend renderer to handle the new type returned by `/validate`.
4. Update docs (`docs/03-REST-API.md`, `docs/04-ADMIN-UI.md`) with the new contract.

### Example filter pattern for asset normalization

```php
$asset = apply_filters( 'rtg_asset_payload', $asset, $request );
```

```php
add_filter( 'rtg_asset_payload', function( $asset ) {
	if ( 'audio' === $asset['type'] && empty( $asset['config']['autoplay'] ) ) {
		$asset['config']['autoplay'] = false;
	}
	return $asset;
} );
```

## 2) Add custom event taxonomy

You can introduce additional event categories while preserving required defaults.

### Example filter for allowed event types

```php
$allowed = apply_filters(
	'rtg_allowed_event_types',
	array( 'page_view', 'download_click', 'video_play', 'video_progress' )
);
```

```php
add_filter( 'rtg_allowed_event_types', function( $allowed ) {
	$allowed[] = 'cta_click';
	return array_values( array_unique( $allowed ) );
} );
```

When adding new events, also:

- Document payload schema.
- Ensure sensitive values are hashed or omitted.
- Keep webhook payloads backward compatible.

## 3) Extend webhook payloads safely

Use additive keys only and avoid removing current fields (`event`, `occurredAt`, `payload`).

### Example payload enrichment filter

```php
$payload = apply_filters( 'rtg_webhook_payload', $payload, $event_name );
```

```php
add_filter( 'rtg_webhook_payload', function( $payload, $event_name ) {
	$payload['plugin_version'] = '0.1.0';
	$payload['source'] = 'rt-gate';
	return $payload;
}, 10, 2 );
```

## 4) Add integration providers

Current integration path is webhook-first (`includes/class-webhook.php`) with OAuth scaffold in `includes/class-salesforce.php`.

Recommended pattern:

1. Keep webhook as stable boundary.
2. Add provider adapter class for the new destination.
3. Trigger adapter from existing event flow behind a feature flag.
4. Add ADR if integration architecture changes.

## Documentation requirements for extension PRs

Update all affected docs:

- `docs/01-ARCHITECTURE.md`
- `docs/03-REST-API.md`
- `docs/05-SECURITY.md`
- `docs/06-SALESFORCE.md` (or provider doc)
- Relevant ADR in `docs/adr/`
