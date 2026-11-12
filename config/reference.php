<?php

// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Loader\ParamConfigurator as Param;

/**
 * This class provides array-shapes for configuring the services and bundles of an application.
 *
 * Services declared with the config() method below are autowired and autoconfigured by default.
 *
 * This is for apps only. Bundles SHOULD NOT use it.
 *
 * Example:
 *
 *     ```php
 *     // config/services.php
 *     namespace Symfony\Component\DependencyInjection\Loader\Configurator;
 *
 *     return App::config([
 *         'services' => [
 *             'App\\' => [
 *                 'resource' => '../src/',
 *             ],
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type ImportsConfig = list<string|array{
 *     resource: string,
 *     type?: string|null,
 *     ignore_errors?: bool|'not_found',
 * }>
 * @psalm-type ParametersConfig = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array<mixed>|Param|null>|Param|null>
 * @psalm-type ArgumentsType = list<mixed>|array<string, mixed>
 * @psalm-type CallType = array<string, ArgumentsType>|array{0:string, 1?:ArgumentsType, 2?:bool}|array{method:string, arguments?:ArgumentsType, returns_clone?:bool}
 * @psalm-type TagsType = list<string|array<string, array<string, mixed>>> // arrays inside the list must have only one element, with the tag name as the key
 * @psalm-type CallbackType = string|array{0:string|ReferenceConfigurator,1:string}|\Closure|ReferenceConfigurator
 * @psalm-type DeprecationType = array{package: string, version: string, message?: string}
 * @psalm-type DefaultsType = array{
 *     public?: bool,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 * }
 * @psalm-type InstanceofType = array{
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     factory?: CallbackType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type DefinitionType = array{
 *     class?: string,
 *     file?: string,
 *     parent?: string,
 *     shared?: bool,
 *     synthetic?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     configurator?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     decorates?: string,
 *     decorates_tag?: string,
 *     decoration_within?: string|list<string>,
 *     decoration_around?: string|list<string>,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 *     from_callable?: CallbackType,
 * }
 * @psalm-type AliasType = string|array{
 *     alias: string,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type PrototypeType = array{
 *     resource: string,
 *     namespace?: string,
 *     exclude?: string|list<string>,
 *     parent?: string,
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type StackType = array{
 *     stack: list<DefinitionType|AliasType|PrototypeType|array<class-string, ArgumentsType|null>>,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 *     decorates?: string,
 *     decorates_tag?: string,
 *     decoration_within?: string|list<string>,
 *     decoration_around?: string|list<string>,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 * }
 * @psalm-type ServicesConfig = array{
 *     _defaults?: DefaultsType,
 *     _instanceof?: array<class-string, InstanceofType>,
 *     ...<string, DefinitionType|AliasType|PrototypeType|StackType|ArgumentsType|null>
 * }
 * @psalm-type ExtensionType = array<mixed>|scalar|\UnitEnum|Param|null
 * @psalm-type RouterConfig = bool|array{
 *     enabled?: bool|Param, // Default: false
 *     resource?: scalar|Param|null, // Default: null
 *     type?: scalar|Param|null,
 *     default_uri?: scalar|Param|null, // The default URI used to generate URLs in a non-HTTP context. // Default: null
 *     http_port?: scalar|Param|null, // Default: 80
 *     https_port?: scalar|Param|null, // Default: 443
 *     strict_requirements?: scalar|Param|null, // Set to true to throw an exception when a parameter does not match the requirements. Set to false to disable exceptions when a parameter does not match the requirements (and return null instead). Set to null to disable parameter checks against requirements. Defaults to true in debug mode and to null otherwise.
 *     utf8?: bool|Param, // Default: true
 * }
 * @psalm-type CacheConfig = array{
 *     prefix_seed?: scalar|Param|null, // Used to namespace cache keys when using several apps with the same shared backend. // Default: "_%kernel.project_dir%.%kernel.container_class%"
 *     app?: scalar|Param|null, // App related cache pools configuration. Cannot be combined with "default_provider". // Default: "cache.adapter.filesystem"
 *     system?: scalar|Param|null, // System related cache pools configuration. // Default: "cache.adapter.system"
 *     directory?: scalar|Param|null, // Default: "%kernel.share_dir%/pools/app"
 *     default_provider?: scalar|Param|null, // DSN of the backend to use for "cache.app"; the adapter is deduced from it. Replaces "app", which cannot be set alongside it.
 *     default_psr6_provider?: scalar|Param|null,
 *     default_redis_provider?: scalar|Param|null, // Default: "redis://localhost"
 *     default_valkey_provider?: scalar|Param|null, // Default: "valkey://localhost"
 *     default_memcached_provider?: scalar|Param|null, // Default: "memcached://localhost"
 *     default_doctrine_dbal_provider?: scalar|Param|null, // Default: "database_connection"
 *     default_pdo_provider?: scalar|Param|null, // Default: null
 *     default_mongodb_provider?: scalar|Param|null, // Default: "mongodb://localhost/app"
 *     pools?: array<string, array{ // Default: []
 *         adapters?: Param|string|list<scalar|Param|null>,
 *         tags?: scalar|Param|null, // Default: null
 *         public?: bool|Param, // Default: false
 *         default_lifetime?: scalar|Param|null, // Default lifetime of the pool.
 *         provider?: scalar|Param|null, // Overwrite the setting from the default provider for this adapter.
 *         early_expiration_message_bus?: scalar|Param|null,
 *         clearer?: scalar|Param|null,
 *         marshaller?: scalar|Param|null, // The marshaller service to use for this pool.
 *     }>,
 * }
 * @psalm-type SerializerConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     enable_attributes?: bool|Param, // Default: true
 *     name_converter?: scalar|Param|null,
 *     circular_reference_handler?: scalar|Param|null,
 *     max_depth_handler?: scalar|Param|null,
 *     mapping?: array{
 *         paths?: list<scalar|Param|null>,
 *     },
 *     default_context?: array<string, mixed>,
 *     named_serializers?: array<string, array{ // Default: []
 *         name_converter?: scalar|Param|null,
 *         default_context?: array<string, mixed>,
 *         include_built_in_normalizers?: bool|Param, // Whether to include the built-in normalizers // Default: true
 *         include_built_in_encoders?: bool|Param, // Whether to include the built-in encoders // Default: true
 *     }>,
 * }
 * @psalm-type ValidationConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     enable_attributes?: bool|Param, // Default: true
 *     static_method?: Param|string|list<scalar|Param|null>,
 *     translation_domain?: scalar|Param|null, // Default: "validators"
 *     email_validation_mode?: "html5"|"html5-allow-no-tld"|"strict"|Param, // Default: "html5"
 *     mapping?: array{
 *         paths?: list<scalar|Param|null>,
 *     },
 *     not_compromised_password?: bool|array{
 *         enabled?: bool|Param|null, // When disabled, compromised passwords will be accepted as valid. Defaults to false when "framework.test" is enabled, to true otherwise. // Default: null
 *         endpoint?: scalar|Param|null, // API endpoint for the NotCompromisedPassword Validator. // Default: null
 *     },
 *     disable_translation?: bool|Param, // Default: false
 *     property_metadata_existence_check?: bool|Param, // When enabled, validateProperty() and validatePropertyValue() throw an exception if no metadata is found for the given property. // Default: false
 *     auto_mapping?: array<string, array{ // Default: []
 *         services?: list<scalar|Param|null>,
 *     }>,
 * }
 * @psalm-type TypeInfoConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     aliases?: array<string, scalar|Param|null>,
 * }
 * @psalm-type PropertyAccessConfig = bool|array{ // Property access configuration
 *     enabled?: bool|Param, // Default: true
 *     magic_call?: bool|Param, // Default: false
 *     magic_get?: bool|Param, // Default: true
 *     magic_set?: bool|Param, // Default: true
 *     throw_exception_on_invalid_index?: bool|Param, // Default: false
 *     throw_exception_on_invalid_property_path?: bool|Param, // Default: true
 *     wildcard_reads?: bool|Param, // Enables reading every element of a collection through a "[*]" wildcard. // Default: false
 * }
 * @psalm-type PropertyInfoConfig = bool|array{ // Property info configuration
 *     enabled?: bool|Param, // Default: true
 *     with_constructor_extractor?: bool|Param, // Registers the constructor extractor. // Default: true
 * }
 * @psalm-type UidConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     default_uuid_version?: 7|6|4|1|Param, // Default: 7
 *     name_based_uuid_version?: 5|3|Param, // Default: 5
 *     name_based_uuid_namespace?: scalar|Param|null,
 *     time_based_uuid_version?: 7|6|1|Param, // Default: 7
 *     time_based_uuid_node?: scalar|Param|null,
 *     uuid47_secret?: scalar|Param|null, // A high-entropy secret used by the "uuid47_transformer" service. Defaults to the "kernel.secret" parameter; the service is not registered when neither is defined. // Default: null
 * }
 * @psalm-type HttpClientConfig = bool|array{
 *     enabled?: bool|Param, // Default: true
 *     max_host_connections?: int|Param, // The maximum number of connections to a single host.
 *     recorder?: bool|array{ // Record HTTP exchanges into HAR files and replay them in tests (see RecorderHttpClient).
 *         enabled?: bool|Param|null, // Defaults to true when "framework.test" is enabled, to false otherwise. // Default: null
 *         redactor?: scalar|Param|null, // Service id of a RedactorInterface; when set, "redact" and "redact_except" are ignored. // Default: null
 *         redact?: list<scalar|Param|null>,
 *         redact_except?: list<scalar|Param|null>,
 *     },
 *     default_options?: array{
 *         vars?: array<string, mixed>,
 *         headers?: array<string, mixed>,
 *         max_redirects?: int|Param, // The maximum number of redirects to follow.
 *         http_version?: scalar|Param|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *         resolve?: array<string, scalar|Param|null>,
 *         proxy?: scalar|Param|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *         no_proxy?: scalar|Param|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *         timeout?: float|Param, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *         max_duration?: float|Param, // The maximum execution time for the request+response as a whole.
 *         max_connect_duration?: float|Param, // The maximum duration allowed for DNS + TCP + TLS connection; a value lower than or equal to 0 means unlimited.
 *         bindto?: scalar|Param|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *         verify_peer?: bool|Param, // Indicates if the peer should be verified in a TLS context.
 *         verify_host?: bool|Param, // Indicates if the host should exist as a certificate common name.
 *         cafile?: scalar|Param|null, // A certificate authority file.
 *         capath?: scalar|Param|null, // A directory that contains multiple certificate authority files.
 *         local_cert?: scalar|Param|null, // A PEM formatted certificate file.
 *         local_pk?: scalar|Param|null, // A private key file.
 *         passphrase?: scalar|Param|null, // The passphrase used to encrypt the "local_pk" file.
 *         ciphers?: scalar|Param|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *         peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *             sha1?: mixed,
 *             pin-sha256?: mixed,
 *             md5?: mixed,
 *         },
 *         crypto_method?: scalar|Param|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *         extra?: array<string, mixed>,
 *         rate_limiter?: scalar|Param|null, // Rate limiter name to use for throttling requests. // Default: null
 *         caching?: bool|array{ // Caching configuration.
 *             enabled?: bool|Param, // Default: false
 *             cache_pool?: string|Param, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *             shared?: bool|Param, // Indicates whether the cache is shared (public) or private. // Default: true
 *             max_ttl?: int|Param, // The maximum TTL (in seconds) allowed for cached responses. // Default: 86400
 *         },
 *         retry_failed?: bool|array{
 *             enabled?: bool|Param, // Default: false
 *             base_uris?: Param|string|list<string|Param>,
 *             retry_strategy?: scalar|Param|null, // service id to override the retry strategy. // Default: null
 *             http_codes?: Param|int|string|array<string, array{ // Default: []
 *                 code?: int|Param,
 *                 methods?: Param|string|list<string|Param>,
 *             }>,
 *             max_retries?: int|Param, // Default: 3
 *             delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *             multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *             max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *             jitter?: float|Param, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *         },
 *     },
 *     mock_response_factory?: scalar|Param|null, // `true` to always return empty 200 responses, or the id of the service to use to generate mock responses - which should be either an invokable or an iterable.
 *     scoped_clients?: array<string, Param|string|array{ // Default: []
 *         scope?: scalar|Param|null, // The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.
 *         base_uri?: scalar|Param|null, // The URI to resolve relative URLs, following rules in RFC 3985, section 2.
 *         auth_basic?: scalar|Param|null, // An HTTP Basic authentication "username:password".
 *         auth_bearer?: scalar|Param|null, // A token enabling HTTP Bearer authorization.
 *         auth_ntlm?: scalar|Param|null, // A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).
 *         query?: array<string, scalar|Param|null>,
 *         mock_response_factory?: scalar|Param|null, // `true` to always return empty 200 responses, `false` to disable mocking, or the id of the service to use to generate mock responses (invokable or iterable).
 *         headers?: array<string, mixed>,
 *         max_redirects?: int|Param, // The maximum number of redirects to follow.
 *         http_version?: scalar|Param|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *         resolve?: array<string, scalar|Param|null>,
 *         proxy?: scalar|Param|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *         no_proxy?: scalar|Param|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *         timeout?: float|Param, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *         max_duration?: float|Param, // The maximum execution time for the request+response as a whole.
 *         max_connect_duration?: float|Param, // The maximum duration allowed for DNS + TCP + TLS connection; a value lower than or equal to 0 means unlimited.
 *         bindto?: scalar|Param|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *         verify_peer?: bool|Param, // Indicates if the peer should be verified in a TLS context.
 *         verify_host?: bool|Param, // Indicates if the host should exist as a certificate common name.
 *         cafile?: scalar|Param|null, // A certificate authority file.
 *         capath?: scalar|Param|null, // A directory that contains multiple certificate authority files.
 *         local_cert?: scalar|Param|null, // A PEM formatted certificate file.
 *         local_pk?: scalar|Param|null, // A private key file.
 *         passphrase?: scalar|Param|null, // The passphrase used to encrypt the "local_pk" file.
 *         ciphers?: scalar|Param|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *         peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *             sha1?: mixed,
 *             pin-sha256?: mixed,
 *             md5?: mixed,
 *         },
 *         crypto_method?: scalar|Param|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *         extra?: array<string, mixed>,
 *         rate_limiter?: scalar|Param|null, // Rate limiter name to use for throttling requests. // Default: null
 *         caching?: bool|array{ // Caching configuration.
 *             enabled?: bool|Param, // Default: false
 *             cache_pool?: string|Param, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *             shared?: bool|Param, // Indicates whether the cache is shared (public) or private. // Default: true
 *             max_ttl?: int|Param, // The maximum TTL (in seconds) allowed for cached responses. // Default: 86400
 *         },
 *         retry_failed?: bool|array{
 *             enabled?: bool|Param, // Default: false
 *             base_uris?: Param|string|list<string|Param>,
 *             retry_strategy?: scalar|Param|null, // service id to override the retry strategy. // Default: null
 *             http_codes?: Param|int|string|array<string, array{ // Default: []
 *                 code?: int|Param,
 *                 methods?: Param|string|list<string|Param>,
 *             }>,
 *             max_retries?: int|Param, // Default: 3
 *             delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *             multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *             max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *             jitter?: float|Param, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *         },
 *     }>,
 * }
 * @psalm-type FrameworkConfig = array{
 *     secret?: scalar|Param|null, // A secret that must not change. The options that sign data default to it and take a list of secrets to rotate theirs.
 *     http_method_override?: bool|Param, // Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. // Default: false
 *     allowed_http_method_override?: null|list<string|Param>,
 *     trust_x_sendfile_type_header?: scalar|Param|null, // Set true to enable support for xsendfile in binary file responses. // Default: "%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%"
 *     ide?: scalar|Param|null, // Deprecated: Setting the "framework.ide.ide" configuration option is deprecated, use the "SYMFONY_IDE" env var instead. // Default: null
 *     test?: bool|Param,
 *     default_locale?: scalar|Param|null, // Default: "en"
 *     set_locale_from_accept_language?: bool|Param, // Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed). // Default: false
 *     set_content_language_from_locale?: bool|Param, // Whether to set the Content-Language HTTP header on the Response using the Request locale. // Default: false
 *     enabled_locales?: list<scalar|Param|null>,
 *     trusted_hosts?: Param|string|list<scalar|Param|null>,
 *     trusted_proxies?: mixed, // Default: ["%env(default::SYMFONY_TRUSTED_PROXIES)%"]
 *     trusted_headers?: Param|string|list<scalar|Param|null>,
 *     error_controller?: scalar|Param|null, // Default: "error_controller"
 *     handle_all_throwables?: bool|Param, // HttpKernel will handle all kinds of \Throwable. // Default: true
 *     csrf_protection?: bool|array{
 *         enabled?: scalar|Param|null, // Default: null
 *         stateless_token_ids?: list<scalar|Param|null>,
 *         check_header?: scalar|Param|null, // Whether to check the CSRF token in a header in addition to a cookie when using stateless protection. // Default: false
 *         cookie_name?: scalar|Param|null, // The name of the cookie to use when using stateless protection. // Default: "csrf-token"
 *     },
 *     form?: bool|array{ // Form configuration
 *         enabled?: bool|Param, // Default: false
 *         csrf_protection?: bool|array{
 *             enabled?: scalar|Param|null, // Default: null
 *             token_id?: scalar|Param|null, // Default: null
 *             field_name?: scalar|Param|null, // Default: "_token"
 *             field_attr?: array<string, scalar|Param|null>,
 *         },
 *     },
 *     http_cache?: bool|array{ // HTTP cache configuration
 *         enabled?: bool|Param, // Default: false
 *         debug?: bool|Param, // Default: "%kernel.debug%"
 *         trace_level?: "none"|"short"|"full"|Param,
 *         trace_header?: scalar|Param|null,
 *         cache_status?: scalar|Param|null, // Enables the RFC 9211 "Cache-Status" response header and names this cache in it, e.g. "Symfony". No header is added when null.
 *         default_ttl?: int|Param,
 *         private_headers?: list<scalar|Param|null>,
 *         skip_response_headers?: list<scalar|Param|null>,
 *         allow_reload?: bool|Param,
 *         allow_revalidate?: bool|Param,
 *         stale_while_revalidate?: int|Param,
 *         stale_if_error?: int|Param,
 *         terminate_on_cache_hit?: bool|Param, // Deprecated: Setting the "framework.http_cache.terminate_on_cache_hit.terminate_on_cache_hit" configuration option is deprecated. It will be removed in version 9.0.
 *     },
 *     esi?: bool|array{ // ESI configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     ssi?: bool|array{ // SSI configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     fragments?: bool|array{ // Fragments configuration
 *         enabled?: bool|Param, // Default: false
 *         hinclude_default_template?: scalar|Param|null, // Deprecated: Setting the "framework.fragments.hinclude_default_template.hinclude_default_template" configuration option is deprecated. It will be removed in version 9.0. // Default: null
 *         path?: scalar|Param|null, // Default: "/_fragment"
 *     },
 *     uri_signer?: array{ // URI signer configuration
 *         secret?: Param|string|list<scalar|Param|null>,
 *         expiration?: int|Param, // Default expiration of signed URIs, in seconds. // Default: null
 *     },
 *     profiler?: bool|array{ // Profiler configuration
 *         enabled?: bool|Param, // Default: false
 *         collect?: bool|Param, // Default: true
 *         collect_parameter?: scalar|Param|null, // The name of the parameter to use to enable or disable collection on a per request basis. // Default: null
 *         only_exceptions?: bool|Param, // Default: false
 *         only_main_requests?: bool|Param, // Default: false
 *         excluded_paths?: Param|string|list<scalar|Param|null>,
 *         excluded_http_codes?: Param|int|string|list<Param|string|list<scalar|Param|null>>,
 *         dsn?: scalar|Param|null, // Default: "file:%kernel.cache_dir%/profiler"
 *         collect_serializer_data?: true|Param, // Deprecated: Setting the "framework.profiler.collect_serializer_data.collect_serializer_data" configuration option is deprecated. It will be removed in version 9.0. // Default: true
 *     },
 *     workflows?: mixed,
 *     router?: RouterConfig,
 *     assets?: mixed,
 *     asset_mapper?: mixed,
 *     translator?: mixed,
 *     validation?: ValidationConfig,
 *     serializer?: SerializerConfig,
 *     property_access?: PropertyAccessConfig,
 *     type_info?: TypeInfoConfig,
 *     property_info?: PropertyInfoConfig,
 *     cache?: CacheConfig,
 *     web_link?: mixed,
 *     lock?: mixed,
 *     semaphore?: mixed,
 *     messenger?: mixed,
 *     scheduler?: mixed,
 *     http_client?: HttpClientConfig,
 *     mailer?: mixed,
 *     notifier?: mixed,
 *     rate_limiter?: mixed,
 *     uid?: UidConfig,
 *     html_sanitizer?: mixed,
 *     webhook?: mixed,
 *     remote_event?: mixed,
 *     json_streamer?: mixed,
 *     session?: bool|array{ // Session configuration
 *         enabled?: bool|Param, // Default: false
 *         storage_factory_id?: scalar|Param|null, // Defaults to "session.storage.factory.mock_file" when "framework.test" is true, to "session.storage.factory.native" otherwise. // Default: null
 *         handler_id?: scalar|Param|null, // Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.
 *         name?: scalar|Param|null,
 *         cookie_lifetime?: scalar|Param|null,
 *         cookie_path?: scalar|Param|null,
 *         cookie_domain?: scalar|Param|null,
 *         cookie_secure?: true|false|"auto"|Param, // Default: "auto"
 *         cookie_httponly?: bool|Param, // Default: true
 *         cookie_samesite?: null|"lax"|"strict"|"none"|Param, // Default: "lax"
 *         use_cookies?: bool|Param,
 *         gc_divisor?: scalar|Param|null,
 *         gc_probability?: scalar|Param|null,
 *         gc_maxlifetime?: scalar|Param|null,
 *         save_path?: scalar|Param|null, // Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.
 *         metadata_update_threshold?: int|Param, // Seconds to wait between 2 session metadata updates. // Default: 0
 *         isolate_attributes?: bool|Param, // Whether to deep-clone the values read from and passed to session attributes, so that only values passed to set() are saved. // Default: false
 *     },
 *     request?: bool|array{ // Request configuration
 *         enabled?: bool|Param, // Default: false
 *         formats?: array<string, Param|string|list<scalar|Param|null>>,
 *         serializer?: scalar|Param|null, // Service id of the serializer that maps request payloads and query strings to controller arguments with #[MapRequestPayload] and #[MapQueryString], e.g. "serializer.api" for the named serializer "api". // Default: null
 *     },
 *     response?: array{ // Response configuration
 *         serializer?: scalar|Param|null, // Service id of the serializer that serializes the values returned by controllers with #[Serialize], e.g. "serializer.api" for the named serializer "api". // Default: null
 *     },
 *     php_errors?: array{ // PHP errors handling configuration
 *         log?: mixed, // Use the application logger instead of the PHP logger for logging PHP errors. // Default: true
 *         throw?: bool|Param|null, // Throw PHP errors as \ErrorException instances. Enabled by default when debug is enabled. // Default: null
 *     },
 *     exceptions?: array<string, array{ // Default: []
 *         log_level?: scalar|Param|null, // The level of log message. Null to let Symfony decide. // Default: null
 *         status_code?: scalar|Param|null, // The status code of the response. Null or 0 to let Symfony decide. // Default: null
 *         log_channel?: scalar|Param|null, // The channel of log message. Null to let Symfony decide. // Default: null
 *     }>,
 *     disallow_search_engine_index?: bool|Param|null, // Enabled by default when debug is enabled. // Default: null
 *     secrets?: bool|array{
 *         enabled?: bool|Param, // Default: true
 *         vault_directory?: scalar|Param|null, // Default: "%kernel.project_dir%/config/secrets/%kernel.runtime_environment%"
 *         local_dotenv_file?: scalar|Param|null, // Default: "%kernel.project_dir%/.env.%kernel.environment%.local"
 *         decryption_env_var?: scalar|Param|null, // Default: "base64:default::SYMFONY_DECRYPTION_SECRET"
 *     },
 * }
 * @psalm-type AiConfig = array{
 *     platform?: array{
 *         albert?: array{
 *             api_key?: string|Param,
 *             base_url?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         amazeeai?: array{
 *             base_url?: string|Param,
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         anthropic?: array{
 *             api_key?: string|Param,
 *             version?: string|Param, // Default: null
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             cache_retention?: "none"|"short"|"long"|Param, // Prompt cache retention policy for Anthropic models // Default: "short"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         azure?: array<string, array{ // Default: []
 *             api_key?: string|Param,
 *             base_url?: string|Param,
 *             deployment?: string|Param,
 *             api_version?: string|Param, // The used API version
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         }>,
 *         bedrock?: array<string, array{ // Default: []
 *             api?: "invoke_model"|"completions"|"responses"|"messages"|Param, // Inference engine and protocol: the SDK-based InvokeModel API, or one of the Bedrock Mantle routes // Default: "invoke_model"
 *             bedrock_runtime_client?: string|Param, // Service ID of the Bedrock runtime client to use; only valid with "api: invoke_model" // Default: null
 *             api_key?: string|Param, // Bedrock API key; when omitted, requests are signed with AWS SigV4. Mantle only
 *             region?: string|Param, // AWS region the Mantle base URL is derived from, defaults to "us-west-2". Mantle only
 *             credential_provider?: string|Param, // Service ID of the AsyncAws credential provider used for SigV4 signing. Mantle only
 *             http_client?: string|Param, // Service ID of the HTTP client to use, defaults to "http_client". Mantle only
 *             path?: string|Param, // Overrides the Mantle request path, for models served on the other path prefix
 *             cache_retention?: "none"|"short"|"long"|Param, // Anthropic Messages prompt-cache retention, defaults to "short"; only valid with "api: messages"
 *             workspace?: string|Param, // Bedrock Mantle workspace ID sent with Anthropic Messages requests; only valid with "api: messages"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         }>,
 *         cache?: array<string, array{ // Default: []
 *             platform?: string|Param,
 *             service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *             cache_key?: string|Param, // Key used to store platform results, if not set, the current platform name will be used, the "prompt_cache_key" can be set during platform call to override this value
 *             ttl?: int|Param,
 *         }>,
 *         cartesia?: array{
 *             api_key?: string|Param,
 *             version?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         cerebras?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         cohere?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         decart?: array{
 *             api_key?: string|Param,
 *             host?: string|Param, // Default: "https://api.decart.ai/v1"
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         deepgram?: array{
 *             api_key?: string|Param,
 *             endpoint?: string|Param, // Deepgram REST API endpoint // Default: "https://api.deepgram.com/v1/"
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         deepseek?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         dockermodelrunner?: array{
 *             host_url?: string|Param, // Default: "http://127.0.0.1:12434"
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         edenai?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 api?: bool|Param, // Fetch the models from the provider API instead of using the bundled list // Default: false
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         elevenlabs?: array{
 *             api_key?: string|Param,
 *             endpoint?: string|Param, // Default: "https://api.elevenlabs.io/v1/"
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         failover?: array<string, array{ // Default: []
 *             platforms?: list<scalar|Param|null>,
 *             rate_limiter?: string|Param,
 *         }>,
 *         fireworks?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         gemini?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         generic?: array<string, array{ // Default: []
 *             base_url?: string|Param,
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *             supports_completions?: bool|Param, // Default: true
 *             supports_embeddings?: bool|Param, // Default: true
 *             completions_path?: string|Param, // Default: "/v1/chat/completions"
 *             embeddings_path?: string|Param, // Default: "/v1/embeddings"
 *         }>,
 *         higgsfield?: array{
 *             api_key?: string|Param,
 *             api_secret?: string|Param,
 *             base_url?: string|Param, // Base URL of the Higgsfield API. Defaults to "https://platform.higgsfield.ai" when null.
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         huggingface?: array{
 *             api_key?: string|Param,
 *             provider?: string|Param, // Inference provider for chat completion, Hugging Face picks one when null // Default: null
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 api?: bool|Param, // Fetch the models from the provider API instead of using the bundled list // Default: false
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         lmstudio?: array{
 *             host_url?: string|Param, // Default: "http://127.0.0.1:1234"
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         minimax?: array{
 *             endpoint?: string|Param, // Default: "https://api.minimax.io/v1"
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         mistral?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         ollama?: array{
 *             endpoint?: string|Param, // Endpoint for Ollama (e.g. "http://127.0.0.1:11434" for local, or a cloud endpoint). If null, the http_client is used as-is and must already be configured with a base URI.
 *             api_key?: string|Param, // API key for Ollama Cloud authentication (optional for local usage)
 *             http_client?: string|Param, // Service ID of the HTTP client to use. When "endpoint" is null, this client must be pre-configured (e.g. with a base_uri). // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         openai?: array{
 *             api_key?: string|Param,
 *             region?: scalar|Param|null, // The region for OpenAI API (EU, US, or null for default) // Default: null
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         openresponses?: array<string, array{ // Default: []
 *             base_url?: string|Param,
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *             responses_path?: string|Param, // Default: "/v1/responses"
 *         }>,
 *         openrouter?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 api?: bool|Param, // Fetch the models from the provider API instead of using the bundled list // Default: false
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         ovh?: array{
 *             api_key?: scalar|Param|null,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         perplexity?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         scaleway?: array{
 *             api_key?: scalar|Param|null,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         together?: array{
 *             api_key?: string|Param,
 *             endpoint?: string|Param, // Base endpoint for the Together API. Defaults to "https://api.together.xyz" when null.
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         transformersphp?: array{
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         typesafe?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         venice?: array{
 *             api_key?: string|Param,
 *             endpoint?: string|Param, // Default: "https://api.venice.ai/api/v1/"
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         vertexai?: array{
 *             location?: string|Param, // Required for the project-scoped endpoint. Must be set together with "project_id". // Default: null
 *             project_id?: string|Param, // Required for the project-scoped endpoint. Must be set together with "location". // Default: null
 *             api_key?: string|Param, // When set without "location" and "project_id", uses the global endpoint. Note: API keys only identify the project for billing and do not provide identity-based access control. For production use with IAM, audit logging, or data residency, prefer the project-scoped endpoint with service account authentication. // Default: null
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *         voyage?: array{
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Service ID of the HTTP client to use // Default: "http_client"
 *             model_catalog?: Param|string|array{ // Model catalog of the platform, a string is short for the "service" option
 *                 service?: string|Param, // Service ID of a custom model catalog to use instead of the bridge one
 *                 models?: array<string, array{ // Default: []
 *                     class?: string|Param, // The fully qualified class name of the model (must extend Symfony\AI\Platform\Model) // Default: "Symfony\\AI\\Platform\\Model"
 *                     capabilities?: list<value-of<\Symfony\AI\Platform\Capability>|\Symfony\AI\Platform\Capability|Param>,
 *                 }>,
 *                 cache?: bool|array{ // Caches the model lookups, "true" is short for enabling it with the defaults
 *                     enabled?: bool|Param, // Default: false
 *                     service?: string|Param, // The cache service id as defined under the "cache" configuration key // Default: "cache.app"
 *                     ttl?: int|Param, // Lifetime of the cached lookups in seconds // Default: 3600
 *                 },
 *             },
 *         },
 *     },
 *     agent?: array<string, array{ // Default: []
 *         platform?: string|Param, // Service name of platform // Default: "Symfony\\AI\\Platform\\PlatformInterface"
 *         model?: mixed,
 *         memory?: mixed, // Memory configuration: string for static memory, or array with "service" key for service reference // Default: null
 *         prompt?: Param|string|array{ // The system prompt configuration
 *             text?: string|Param, // The system prompt text
 *             file?: string|Param, // Path to file containing the system prompt
 *             include_tools?: bool|Param, // Include tool definitions at the end of the system prompt // Default: false
 *             enable_translation?: bool|Param, // Enable translation for the system prompt // Default: false
 *             translation_domain?: string|Param, // The translation domain for the system prompt // Default: null
 *         },
 *         tools?: bool|array{ // Tools are opt-in: set to true to inject all services tagged with "ai.tool", or configure an explicit list of tools. When the option is omitted (or set to null or false), no tools are registered.
 *             enabled?: bool|Param, // Default: false
 *             execution_strategy?: scalar|Param|null, // The tool execution strategy. Built-in options are "sequential" (default) and "fiber". A custom service ID implementing ToolExecutorInterface can also be provided. // Default: null
 *             services?: list<Param|string|array{ // Default: []
 *                 service?: string|Param,
 *                 agent?: string|Param,
 *                 mcp_server?: string|Param, // A remote MCP server whose tools are exposed to this agent, as "<client>.<server>" referencing a connection configured under "mcp.clients".
 *                 name?: string|Param,
 *                 description?: string|Param,
 *                 method?: string|Param,
 *                 prefix?: string|Param, // Only with "mcp_server": prefix put in front of every remote tool name. Defaults to "<server>_".
 *             }>,
 *         },
 *         include_sources?: bool|Param, // Include sources exposed by tools as part of the tool result metadata // Default: false
 *         message_store?: string|Param, // Service name of the message store continuing and saving the conversations of the agent by their ID // Default: null
 *         max_tool_calls?: scalar|Param|null, // Maximum number of tool calls per agent call, null to disable // Default: 50
 *         fault_tolerant_toolbox?: bool|Param, // Continue the agent run even if a tool call fails // Default: true
 *         speech?: bool|array{ // Speech (TTS/STT) decorator configuration
 *             enabled?: bool|Param, // Default: true
 *             text_to_speech_platform?: string|Param, // Service name of the TTS platform (e.g. ai.platform.elevenlabs). // Default: null
 *             speech_to_text_platform?: string|Param, // Service name of the STT platform (e.g. ai.platform.openai). // Default: null
 *             tts_model?: string|Param, // Text-to-speech model name // Default: null
 *             tts_options?: mixed, // Provider-specific TTS options // Default: []
 *             stt_model?: string|Param, // Speech-to-text model name // Default: null
 *             stt_options?: mixed, // Provider-specific STT options // Default: []
 *         },
 *     }>,
 *     multi_agent?: array<string, array{ // Default: []
 *         orchestrator?: string|Param, // Service ID of the orchestrator agent
 *         handoffs?: array<string, list<scalar|Param|null>>,
 *         fallback?: string|Param, // Service ID of the fallback agent for unmatched requests
 *     }>,
 *     store?: array{
 *         azuresearch?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             api_version?: string|Param,
 *             index_name?: string|Param, // The name of the store will be used if the "index_name" option is not set
 *             http_client?: string|Param, // Default: "http_client"
 *             vector_field?: string|Param, // Default: "vector"
 *         }>,
 *         cache?: array<string, array{ // Default: []
 *             service?: string|Param, // Default: "cache.app"
 *             cache_key?: string|Param, // The name of the store will be used if the key is not set.
 *             strategy?: string|Param, // Default: "cosine"
 *         }>,
 *         chromadb?: array<string, array{ // Default: []
 *             client?: string|Param, // Default: "Codewithkyrian\\ChromaDB\\Client"
 *             collection?: string|Param,
 *             embedding_function?: string|Param, // Service id of a Codewithkyrian\ChromaDB\Embeddings\EmbeddingFunction, required to query the store with a TextQuery.
 *         }>,
 *         clickhouse?: array<string, array{ // Default: []
 *             dsn?: string|Param,
 *             http_client?: string|Param,
 *             database?: string|Param,
 *             table?: string|Param,
 *         }>,
 *         cloudflare?: array<string, array{ // Default: []
 *             account_id?: string|Param,
 *             api_key?: string|Param,
 *             index_name?: string|Param,
 *             http_client?: string|Param, // Default: "http_client"
 *             dimensions?: int|Param, // Default: 1536
 *             metric?: string|Param, // Default: "cosine"
 *             endpoint?: string|Param,
 *         }>,
 *         doctrine?: array<string, array{ // Default: []
 *             entity?: string|Param, // FQCN of the entity with #[Embedding] on a field, or on the class when it is mirrored.
 *             metric?: "cosine"|"euclidean"|"inner_product"|Param, // Metric to use for vector similarity search. // Default: "cosine"
 *             entity_manager?: string|Param, // Service id of the entity manager owning the entity. // Default: "doctrine.orm.entity_manager"
 *             vectorizer?: string|Param, // Service id of the vectorizer embedding the entities on flush and through "ai:store:index". // Default: null
 *             mirror_to?: string|Param, // Service id of the store to mirror the entity into, needs a "vectorizer" and #[Embedding] on the entity class. // Default: null
 *         }>,
 *         elasticsearch?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             index_name?: string|Param,
 *             vectors_field?: string|Param, // Default: "_vectors"
 *             dimensions?: int|Param, // Default: 1536
 *             metric?: string|Param, // Default: "cosine"
 *             http_client?: string|Param,
 *         }>,
 *         manticoresearch?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             table?: string|Param,
 *             field?: string|Param, // Default: "_vectors"
 *             type?: string|Param, // Default: "hnsw"
 *             metric?: string|Param, // Default: "cosine"
 *             dimensions?: int|Param, // Default: 1536
 *             quantization?: string|Param,
 *             http_client?: string|Param,
 *         }>,
 *         mariadb?: array<string, array{ // Default: []
 *             connection?: string|Param,
 *             table_name?: string|Param,
 *             index_name?: string|Param,
 *             vector_field_name?: string|Param,
 *             dimensions?: int|Param, // Number of dimensions of the vector column created by "ai:store:setup"
 *             metric?: "cosine"|"euclidean"|"distance"|Param, // Distance metric to use for vector similarity search // Default: "euclidean"
 *         }>,
 *         meilisearch?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             index_name?: string|Param,
 *             http_client?: string|Param, // Default: "http_client"
 *             embedder?: string|Param, // Default: "default"
 *             vector_field?: string|Param, // Default: "_vectors"
 *             dimensions?: int|Param, // Default: 1536
 *             semantic_ratio?: float|Param, // The ratio between semantic (vector) and full-text search (0.0 to 1.0). Default: 1.0 (100% semantic) // Default: 1.0
 *         }>,
 *         memory?: array<string, array{ // Default: []
 *             strategy?: string|Param,
 *         }>,
 *         milvus?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             database?: string|Param,
 *             collection?: string|Param,
 *             vector_field?: string|Param, // Default: "_vectors"
 *             dimensions?: int|Param, // Default: 1536
 *             metric?: string|Param, // Default: "COSINE"
 *             http_client?: string|Param,
 *         }>,
 *         mongodb?: array<string, array{ // Default: []
 *             client?: string|Param, // Default: "MongoDB\\Client"
 *             database?: string|Param,
 *             collection?: string|Param,
 *             index_name?: string|Param,
 *             vector_field?: string|Param, // Default: "vector"
 *             bulk_write?: bool|Param, // Default: false
 *             dimensions?: int|Param, // Number of dimensions of the vector index created by "ai:store:setup"
 *             metric?: "euclidean"|"cosine"|"dotProduct"|Param, // Distance function of the vector index created by "ai:store:setup"
 *             index_fields?: mixed, // Extra fields of the vector index created by "ai:store:setup", like filter fields
 *         }>,
 *         neo4j?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             username?: string|Param,
 *             password?: string|Param,
 *             database?: string|Param,
 *             vector_index_name?: string|Param,
 *             node_name?: string|Param,
 *             vector_field?: string|Param, // Default: "embeddings"
 *             dimensions?: int|Param, // Default: 1536
 *             metric?: string|Param, // Default: "cosine"
 *             quantization?: bool|Param,
 *             http_client?: string|Param,
 *         }>,
 *         opensearch?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             index_name?: string|Param,
 *             vectors_field?: string|Param, // Default: "_vectors"
 *             dimensions?: int|Param, // Default: 1536
 *             metric?: string|Param, // Default: "l2"
 *             http_client?: string|Param,
 *         }>,
 *         pinecone?: array<string, array{ // Default: []
 *             client?: string|Param, // Default: "Probots\\Pinecone\\Client"
 *             index_name?: string|Param,
 *             namespace?: string|Param,
 *             filter?: list<scalar|Param|null>,
 *             top_k?: int|Param,
 *             dimensions?: int|Param, // Number of dimensions of the index created by "ai:store:setup"
 *             metric?: "cosine"|"euclidean"|"dotproduct"|Param, // Distance metric of the index created by "ai:store:setup"
 *             cloud?: string|Param, // Cloud of the serverless index created by "ai:store:setup"
 *             region?: string|Param, // Region of the serverless index created by "ai:store:setup"
 *         }>,
 *         postgres?: array<string, array{ // Default: []
 *             dsn?: string|Param,
 *             username?: string|Param,
 *             password?: string|Param,
 *             table_name?: string|Param,
 *             vector_field?: string|Param, // Default: "embedding"
 *             metric?: "cosine"|"inner_product"|"l1"|"l2"|Param, // Distance metric to use for vector similarity search // Default: "l2"
 *             lang?: string|Param, // Default: "english"
 *             dbal_connection?: string|Param,
 *             dimensions?: int|Param, // Number of dimensions of the vector column created by "ai:store:setup"
 *             vector_type?: "vector"|"halfvec"|Param, // Type of the vector column created by "ai:store:setup"
 *             index_method?: string|Param, // The pgvector index method, "hnsw" by default.
 *             index_opclass?: string|Param, // The operator class of the index, the one of the distance by default (e.g. "vector_l2_ops").
 *         }>,
 *         qdrant?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             collection_name?: string|Param, // The name of the store will be used if the "collection_name" is not set
 *             http_client?: string|Param, // Default: "http_client"
 *             dimensions?: int|Param, // Default: 1536
 *             metric?: string|Param, // Default: "Cosine"
 *             async?: bool|Param, // Default: false
 *         }>,
 *         redis?: array<string, array{ // Default: []
 *             connection_parameters?: mixed, // see https://github.com/phpredis/phpredis?tab=readme-ov-file#example-1
 *             client?: string|Param, // a service id of a Redis client
 *             index_name?: string|Param,
 *             key_prefix?: string|Param, // Default: "vector:"
 *             metric?: "COSINE"|"L2"|"IP"|Param, // Distance metric to use for vector similarity search // Default: "COSINE"
 *             dimensions?: int|Param, // Number of dimensions of the index created by "ai:store:setup"
 *         }>,
 *         s3vectors?: array<string, array{ // Default: []
 *             client?: string|Param, // Service reference to an existing S3VectorsClient
 *             configuration?: list<scalar|Param|null>,
 *             vector_bucket_name?: string|Param,
 *             index_name?: string|Param,
 *             filter?: list<mixed>,
 *             dimensions?: int|Param, // Number of dimensions of the index created by "ai:store:setup"
 *             metric?: "cosine"|"euclidean"|Param, // Distance metric of the index created by "ai:store:setup"
 *             top_k?: int|Param, // Default number of results to return // Default: 3
 *         }>,
 *         sqlite?: array<string, array{ // Default: []
 *             dsn?: string|Param,
 *             connection?: string|Param,
 *             table_name?: string|Param,
 *             strategy?: string|Param,
 *             vec?: bool|Param, // Default: false
 *             metric?: "cosine"|"L2"|Param, // Default: "cosine"
 *             dimensions?: int|Param, // Default: 1536
 *         }>,
 *         supabase?: array<string, array{ // Default: []
 *             http_client?: string|Param, // Service ID of the HTTP client to use
 *             url?: string|Param,
 *             api_key?: string|Param,
 *             table?: string|Param,
 *             vector_field?: string|Param, // Default: "embedding"
 *             dimensions?: int|Param, // Default: 1536
 *             function_name?: string|Param, // Default: "match_documents"
 *         }>,
 *         surrealdb?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             username?: string|Param,
 *             password?: string|Param,
 *             namespace?: string|Param,
 *             database?: string|Param,
 *             table?: string|Param,
 *             http_client?: string|Param, // Default: "http_client"
 *             vector_field?: string|Param, // Default: "_vectors"
 *             metric?: string|Param, // Default: "cosine"
 *             dimensions?: int|Param, // Default: 1536
 *             namespaced_user?: bool|Param,
 *         }>,
 *         typesense?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             collection?: string|Param,
 *             http_client?: string|Param, // Default: "http_client"
 *             vector_field?: string|Param, // Default: "_vectors"
 *             dimensions?: int|Param, // Default: 1536
 *         }>,
 *         weaviate?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             http_client?: string|Param, // Default: "http_client"
 *             collection?: string|Param, // The name of the store will be used if the "collection" is not set
 *         }>,
 *         vektor?: array<string, array{ // Default: []
 *             storage_path?: string|Param, // Default: "%kernel.project_dir%/var/share"
 *             dimensions?: int|Param, // Default: 1536
 *         }>,
 *     },
 *     message_store?: array{
 *         cache?: array<string, array{ // Default: []
 *             service?: string|Param, // Default: "cache.app"
 *             key?: string|Param, // The name of the message store will be used if the key is not set
 *             ttl?: int|Param,
 *         }>,
 *         cloudflare?: array<string, array{ // Default: []
 *             account_id?: string|Param,
 *             api_key?: string|Param,
 *             namespace?: string|Param,
 *             endpoint_url?: string|Param, // If the version of the Cloudflare API is updated, use this key to support it.
 *         }>,
 *         doctrine?: array{
 *             dbal?: array<string, array{ // Default: []
 *                 connection?: string|Param,
 *                 table_name?: string|Param, // The name of the message store will be used if the table_name is not set
 *             }>,
 *         },
 *         meilisearch?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             api_key?: string|Param,
 *             index_name?: string|Param,
 *         }>,
 *         memory?: array<string, array{ // Default: []
 *         }>,
 *         mongodb?: array<string, array{ // Default: []
 *             client?: string|Param, // Default: "MongoDB\\Client"
 *             database?: string|Param,
 *             collection?: string|Param,
 *         }>,
 *         pogocache?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             password?: string|Param,
 *             key?: string|Param,
 *         }>,
 *         redis?: array<string, array{ // Default: []
 *             connection_parameters?: mixed, // see https://github.com/phpredis/phpredis?tab=readme-ov-file#example-1
 *             client?: string|Param, // a service id of a Redis client
 *             endpoint?: string|Param,
 *             index_name?: string|Param,
 *         }>,
 *         session?: array<string, array{ // Default: []
 *             identifier?: string|Param,
 *         }>,
 *         surrealdb?: array<string, array{ // Default: []
 *             endpoint?: string|Param,
 *             username?: string|Param,
 *             password?: string|Param,
 *             namespace?: string|Param,
 *             database?: string|Param,
 *             table?: string|Param,
 *             namespaced_user?: bool|Param, // Using a namespaced user is a good practice to prevent any undesired access to a specific table, see https://surrealdb.com/docs/surrealdb/reference-guide/security-best-practices
 *         }>,
 *     },
 *     vectorizer?: array<string, array{ // Default: []
 *         platform?: string|Param, // Service name of platform // Default: "Symfony\\AI\\Platform\\PlatformInterface"
 *         model?: mixed,
 *     }>,
 *     indexer?: array<string, array{ // Default: []
 *         loader?: string|Param, // Service name of loader // Default: null
 *         source?: mixed, // Source identifier (file path, URL, etc.) or array of sources // Default: null
 *         transformers?: list<scalar|Param|null>,
 *         filters?: list<scalar|Param|null>,
 *         vectorizer?: scalar|Param|null, // Service name of vectorizer // Default: "Symfony\\AI\\Store\\Document\\VectorizerInterface"
 *         store?: string|Param, // Service name of store // Default: "Symfony\\AI\\Store\\StoreInterface"
 *     }>,
 *     retriever?: array<string, array{ // Default: []
 *         vectorizer?: scalar|Param|null, // Service name of vectorizer // Default: "Symfony\\AI\\Store\\Document\\VectorizerInterface"
 *         store?: string|Param, // Service name of store // Default: "Symfony\\AI\\Store\\StoreInterface"
 *     }>,
 * }
 * @psalm-type TwigConfig = array{
 *     form_themes?: list<scalar|Param|null>,
 *     globals?: array<string, array{ // Default: []
 *         id?: scalar|Param|null,
 *         type?: scalar|Param|null,
 *         value?: mixed,
 *         ...<string, mixed>
 *     }>,
 *     autoescape_service?: scalar|Param|null, // Default: null
 *     autoescape_service_method?: scalar|Param|null, // Default: null
 *     cache?: scalar|Param|null, // Default: true
 *     charset?: scalar|Param|null, // Default: "%kernel.charset%"
 *     debug?: bool|Param, // Default: "%kernel.debug%"
 *     strict_variables?: bool|Param, // Default: "%kernel.debug%"
 *     auto_reload?: scalar|Param|null,
 *     optimizations?: int|Param,
 *     default_path?: scalar|Param|null, // The default path used to load templates. // Default: "%kernel.project_dir%/templates"
 *     file_name_pattern?: Param|string|list<scalar|Param|null>,
 *     paths?: array<string, mixed>,
 *     date?: array{ // The default format options used by the date filter.
 *         format?: scalar|Param|null, // Default: "F j, Y H:i"
 *         interval_format?: scalar|Param|null, // Default: "%d days"
 *         timezone?: scalar|Param|null, // The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used. // Default: null
 *     },
 *     number_format?: array{ // The default format options for the number_format filter.
 *         decimals?: int|Param, // Default: 0
 *         decimal_point?: scalar|Param|null, // Default: "."
 *         thousands_separator?: scalar|Param|null, // Default: ","
 *     },
 *     mailer?: array{
 *         html_to_text_converter?: scalar|Param|null, // A service implementing the "Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface". // Default: null
 *     },
 * }
 * @psalm-type ConfigType = array{
 *     imports?: ImportsConfig,
 *     parameters?: ParametersConfig,
 *     services?: ServicesConfig,
 *     router?: RouterConfig,
 *     cache?: CacheConfig,
 *     serializer?: SerializerConfig,
 *     validation?: ValidationConfig,
 *     type_info?: TypeInfoConfig,
 *     property_access?: PropertyAccessConfig,
 *     property_info?: PropertyInfoConfig,
 *     uid?: UidConfig,
 *     http_client?: HttpClientConfig,
 *     framework?: FrameworkConfig,
 *     ai?: AiConfig,
 *     twig?: TwigConfig,
 *     "when@dev"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         router?: RouterConfig,
 *         cache?: CacheConfig,
 *         serializer?: SerializerConfig,
 *         validation?: ValidationConfig,
 *         type_info?: TypeInfoConfig,
 *         property_access?: PropertyAccessConfig,
 *         property_info?: PropertyInfoConfig,
 *         uid?: UidConfig,
 *         http_client?: HttpClientConfig,
 *         framework?: FrameworkConfig,
 *         ai?: AiConfig,
 *         twig?: TwigConfig,
 *     },
 *     "when@prod"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         router?: RouterConfig,
 *         cache?: CacheConfig,
 *         serializer?: SerializerConfig,
 *         validation?: ValidationConfig,
 *         type_info?: TypeInfoConfig,
 *         property_access?: PropertyAccessConfig,
 *         property_info?: PropertyInfoConfig,
 *         uid?: UidConfig,
 *         http_client?: HttpClientConfig,
 *         framework?: FrameworkConfig,
 *         ai?: AiConfig,
 *         twig?: TwigConfig,
 *     },
 *     "when@test"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         router?: RouterConfig,
 *         cache?: CacheConfig,
 *         serializer?: SerializerConfig,
 *         validation?: ValidationConfig,
 *         type_info?: TypeInfoConfig,
 *         property_access?: PropertyAccessConfig,
 *         property_info?: PropertyInfoConfig,
 *         uid?: UidConfig,
 *         http_client?: HttpClientConfig,
 *         framework?: FrameworkConfig,
 *         ai?: AiConfig,
 *         twig?: TwigConfig,
 *     },
 *     ...<string, ExtensionType|array{ // extra keys must follow the when@%env% pattern or match an extension alias
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ...<string, ExtensionType>,
 *     }>
 * }
 */
final class App
{
    /**
     * @param ConfigType $config
     *
     * @psalm-return ConfigType
     */
    public static function config(array $config): array
    {
        /** @var ConfigType $config */
        $config = AppReference::config($config);

        return $config;
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the routes of an application.
 *
 * Example:
 *
 *     ```php
 *     // config/routes.php
 *     namespace Symfony\Component\Routing\Loader\Configurator;
 *
 *     return Routes::config([
 *         'controllers' => [
 *             'resource' => 'routing.controllers',
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type RouteConfig = array{
 *     path: string|array<string,string>,
 *     controller?: string,
 *     template?: string,
 *     redirect_to_route?: string,
 *     redirect_to?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     add_condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 *     firewall?: string,
 * }
 * @psalm-type ImportConfig = array{
 *     resource: string,
 *     type?: string,
 *     exclude?: string|list<string>,
 *     prefix?: string|array<string,string>,
 *     name_prefix?: string,
 *     trailing_slash_on_root?: bool,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     add_condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 *     firewall?: string,
 * }
 * @psalm-type AliasConfig = array{
 *     alias: string,
 *     deprecated?: array{package:string, version:string, message?:string},
 * }
 * @psalm-type RoutesConfig = array{
 *     "when@dev"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@prod"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@test"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     ...<string, RouteConfig|ImportConfig|AliasConfig>
 * }
 */
final class Routes
{
    /**
     * @param RoutesConfig $config
     *
     * @psalm-return RoutesConfig
     */
    public static function config(array $config): array
    {
        return $config;
    }
}
