<?php

declare(strict_types=1);

use FlatFileCms\Admin\AdminAuthController;
use FlatFileCms\Admin\AdminCollectionController;
use FlatFileCms\Admin\AdminLayout;
use FlatFileCms\Admin\AdminMediaController;
use FlatFileCms\Admin\AdminPageBuilderController;
use FlatFileCms\Admin\AdminPageController;
use FlatFileCms\Admin\AdminRedirectController;
use FlatFileCms\Admin\AdminSettingsController;
use FlatFileCms\Admin\AdminUserController;
use FlatFileCms\Admin\AdminView;
use FlatFileCms\Admin\BlockFormDataMapper;
use FlatFileCms\Admin\BlockFormRenderer;
use FlatFileCms\Admin\PasswordResetController;
use FlatFileCms\Api\ApiResponseFactory;
use FlatFileCms\Api\CollectionSerializer;
use FlatFileCms\Api\PageSerializer;
use FlatFileCms\Api\PublicApiController;
use FlatFileCms\Audit\AuditLogger;
use FlatFileCms\Auth\AdminUserManager;
use FlatFileCms\Auth\Authenticator;
use FlatFileCms\Auth\CsrfTokenManager;
use FlatFileCms\Auth\NativeSessionStore;
use FlatFileCms\Auth\PasswordChanger;
use FlatFileCms\Auth\PasswordHasher;
use FlatFileCms\Auth\PasswordPolicy;
use FlatFileCms\Auth\PasswordResetRepository;
use FlatFileCms\Auth\PasswordResetService;
use FlatFileCms\Auth\RateLimiter;
use FlatFileCms\Auth\SessionStore;
use FlatFileCms\Auth\UserRepository;
use FlatFileCms\Auth\WebAuthnCredentialRepository;
use FlatFileCms\Auth\WebAuthnService;
use FlatFileCms\Blocks\BlockProcessor;
use FlatFileCms\Blocks\BlockRegistry;
use FlatFileCms\Blocks\BlockValidator;
use FlatFileCms\Blocks\BuiltinFieldTypes;
use FlatFileCms\Blocks\Field\FieldTypeRegistry;
use FlatFileCms\Collections\CollectionManager;
use FlatFileCms\Collections\CollectionRepository;
use FlatFileCms\Collections\CollectionService;
use FlatFileCms\Config\ConfigurationManager;
use FlatFileCms\Config\ConfigurationRepository;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Config\SiteTextRepository;
use FlatFileCms\Content\ContentFileIndex;
use FlatFileCms\Content\PageBlockManager;
use FlatFileCms\Content\PageManager;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Core\Application;
use FlatFileCms\Core\Container;
use FlatFileCms\Core\Environment;
use FlatFileCms\Core\ProductionGuard;
use FlatFileCms\Domain\Localization\LocalizedDataResolver;
use FlatFileCms\Http\ErrorHandler;
use FlatFileCms\Http\HtmlResponseFactory;
use FlatFileCms\Http\Router;
use FlatFileCms\Http\TrustedProxyResolver;
use FlatFileCms\Infrastructure\Database\Database;
use FlatFileCms\Infrastructure\Filesystem\AtomicFileWriter;
use FlatFileCms\Infrastructure\Filesystem\DirectoryOperator;
use FlatFileCms\Infrastructure\Filesystem\FileLockManager;
use FlatFileCms\Infrastructure\Filesystem\SafePathResolver;
use FlatFileCms\Infrastructure\Yaml\YamlFileCache;
use FlatFileCms\Infrastructure\Yaml\YamlFileRepository;
use FlatFileCms\Infrastructure\Yaml\YamlParser;
use FlatFileCms\Logging\LoggerFactory;
use FlatFileCms\Logging\RuntimeErrorLogger;
use FlatFileCms\Mail\Mailer;
use FlatFileCms\Mail\MailException;
use FlatFileCms\Mail\SmtpMailer;
use FlatFileCms\Media\MediaInspector;
use FlatFileCms\Media\MediaManager;
use FlatFileCms\Media\MediaOutputEnricher;
use FlatFileCms\Media\MediaRepository;
use FlatFileCms\Media\MediaUrlGenerator;
use FlatFileCms\Media\MediaVariantService;
use FlatFileCms\Media\PublicMediaController;
use FlatFileCms\Media\RasterImageProcessor;
use FlatFileCms\Media\SvgSanitizer;
use FlatFileCms\Navigation\NavigationManager;
use FlatFileCms\Navigation\NavigationRepository;
use FlatFileCms\Presentation\CollectionViewModelFactory;
use FlatFileCms\Presentation\PageViewModelFactory;
use FlatFileCms\Redirects\RedirectController;
use FlatFileCms\Redirects\RedirectManager;
use FlatFileCms\Redirects\RedirectRepository;
use FlatFileCms\Rendering\AssetCollector;
use FlatFileCms\Rendering\AssetPublisher;
use FlatFileCms\Rendering\BlockRenderer;
use FlatFileCms\Rendering\CollectionRenderer;
use FlatFileCms\Rendering\LayoutRegistry;
use FlatFileCms\Rendering\LayoutRenderer;
use FlatFileCms\Rendering\MarkdownRenderer;
use FlatFileCms\Rendering\OutputBuffer;
use FlatFileCms\Rendering\PageRenderer;
use FlatFileCms\Rendering\PartialRegistry;
use FlatFileCms\Rendering\PartialRenderer;
use FlatFileCms\Rendering\SiteController;
use FlatFileCms\Seo\SeoResolver;
use FlatFileCms\Seo\SitemapController;
use FlatFileCms\Seo\SiteTextController;
use Psr\Log\LoggerInterface;

$projectRoot = dirname(__DIR__);

require "{$projectRoot}/vendor/autoload.php";

$environment = Environment::load($projectRoot);
ProductionGuard::initialize($environment);
$logger = LoggerFactory::create($environment);
RuntimeErrorLogger::register($logger);
$container = new Container();
$container->set(Environment::class, static fn(): Environment => $environment);
$container->set(LoggerInterface::class, static fn(): LoggerInterface => $logger);
$container->set(
    TrustedProxyResolver::class,
    static fn(Container $container): TrustedProxyResolver => TrustedProxyResolver::fromString(
        $container->get(Environment::class)->get('TRUSTED_PROXIES', ''),
    ),
);
$container->set(
    Database::class,
    static fn(Container $container): Database => new Database(
        $container->get(Environment::class)->projectRoot() . '/storage/database/cms.sqlite',
    ),
);
$container->set(
    UserRepository::class,
    static fn(Container $container): UserRepository => new UserRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->set(
    WebAuthnCredentialRepository::class,
    static fn(Container $container): WebAuthnCredentialRepository => new WebAuthnCredentialRepository(
        $container->get(Database::class)->connection(),
    ),
);
$container->set(PasswordHasher::class, static fn(): PasswordHasher => new PasswordHasher());
$container->set(PasswordPolicy::class, static fn(): PasswordPolicy => new PasswordPolicy());
$container->set(
    PasswordChanger::class,
    static fn(Container $container): PasswordChanger => new PasswordChanger(
        $container->get(UserRepository::class),
        $container->get(PasswordHasher::class),
        $container->get(PasswordPolicy::class),
    ),
);
$container->set(
    SessionStore::class,
    static fn(Container $container): SessionStore => new NativeSessionStore(
        $container->get(Environment::class)->projectRoot() . '/storage/sessions',
        $container->get(Environment::class)->get('SESSION_NAME', 'flatfile_cms_session'),
        $container->get(Environment::class)->integer('SESSION_LIFETIME', 7200),
        $container->get(Environment::class)->boolean('SESSION_COOKIE_SECURE', true),
        $container->get(Environment::class)->get('SESSION_COOKIE_SAME_SITE', 'Lax'),
    ),
);
$container->set(
    CsrfTokenManager::class,
    static fn(Container $container): CsrfTokenManager => new CsrfTokenManager($container->get(SessionStore::class)),
);
$container->set(
    RateLimiter::class,
    static fn(Container $container): RateLimiter => new RateLimiter(
        $container->get(Database::class)->connection(),
        $container->get(Environment::class)->get('APP_SECRET'),
        $container->get(Environment::class)->integer('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        $container->get(Environment::class)->integer('AUTH_LOGIN_WINDOW_SECONDS', 900),
    ),
);
$container->set(
    Authenticator::class,
    static fn(Container $container): Authenticator => new Authenticator(
        $container->get(UserRepository::class),
        $container->get(WebAuthnCredentialRepository::class),
        $container->get(PasswordHasher::class),
        $container->get(SessionStore::class),
        $container->get(RateLimiter::class),
        $container->get(Environment::class)->integer('SESSION_ABSOLUTE_LIFETIME', 28_800),
        $container->get(Environment::class)->integer('SESSION_IDLE_LIFETIME', 7_200),
    ),
);
$container->set(
    WebAuthnService::class,
    static fn(Container $container): WebAuthnService => new WebAuthnService(
        $container->get(WebAuthnCredentialRepository::class),
        $container->get(SessionStore::class),
        $container->get(Environment::class)->get('WEBAUTHN_RP_NAME', 'FlatFile CMS'),
        $container->get(Environment::class)->get('WEBAUTHN_RP_ID'),
    ),
);
$container->set(
    AdminView::class,
    static fn(Container $container): AdminView => new AdminView(
        $container->get(Environment::class)->projectRoot(),
        $container->get(OutputBuffer::class),
    ),
);
$container->set(
    AdminLayout::class,
    static fn(Container $container): AdminLayout => new AdminLayout(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(AdminView::class),
    ),
);
$container->set(
    AdminAuthController::class,
    static fn(Container $container): AdminAuthController => new AdminAuthController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(PasswordChanger::class),
        $container->get(PasswordHasher::class),
        $container->get(WebAuthnCredentialRepository::class),
        $container->get(WebAuthnService::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    SafePathResolver::class,
    static fn(Container $container): SafePathResolver => new SafePathResolver(
        $container->get(Environment::class)->projectRoot(),
    ),
);
$container->set(
    AuditLogger::class,
    static fn(Container $container): AuditLogger => new AuditLogger(
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    FileLockManager::class,
    static fn(Container $container): FileLockManager => new FileLockManager(
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    AtomicFileWriter::class,
    static fn(Container $container): AtomicFileWriter => new AtomicFileWriter(
        $container->get(SafePathResolver::class),
        $container->get(FileLockManager::class),
    ),
);
$container->set(
    SiteTextRepository::class,
    static fn(Container $container): SiteTextRepository => new SiteTextRepository(
        $container->get(SafePathResolver::class),
        $container->get(AtomicFileWriter::class),
    ),
);
$container->set(YamlParser::class, static fn(): YamlParser => new YamlParser());
$container->set(
    YamlFileCache::class,
    static fn(Container $container): YamlFileCache => new YamlFileCache(
        $container->get(Environment::class)->boolean('YAML_CACHE_JSON_ENABLED', true),
        $container->get(SafePathResolver::class),
        $container->get(AtomicFileWriter::class),
        $container->get(Environment::class)->boolean('YAML_CACHE_SERIALIZE_ENABLED', false),
    ),
);
$container->set(
    YamlFileRepository::class,
    static fn(Container $container): YamlFileRepository => new YamlFileRepository(
        $container->get(SafePathResolver::class),
        $container->get(YamlParser::class),
        $container->get(YamlFileCache::class),
        $container->get(AtomicFileWriter::class),
    ),
);
$container->set(
    RedirectRepository::class,
    static fn(Container $container): RedirectRepository => new RedirectRepository(
        $container->get(YamlFileRepository::class),
    ),
);
$container->set(
    RedirectManager::class,
    static fn(Container $container): RedirectManager => new RedirectManager(
        $container->get(RedirectRepository::class),
    ),
);
$container->set(
    RedirectController::class,
    static fn(Container $container): RedirectController => new RedirectController(
        $container->get(RedirectRepository::class),
    ),
);
$container->set(
    LanguageRepository::class,
    static fn(Container $container): LanguageRepository => new LanguageRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    ConfigurationRepository::class,
    static fn(Container $container): ConfigurationRepository => new ConfigurationRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
    ),
);
$container->set(SvgSanitizer::class, static fn(): SvgSanitizer => new SvgSanitizer());
$container->set(
    MediaInspector::class,
    static fn(Container $container): MediaInspector => new MediaInspector($container->get(SvgSanitizer::class)),
);
$container->set(RasterImageProcessor::class, static fn(): RasterImageProcessor => new RasterImageProcessor());
$container->set(MediaUrlGenerator::class, static fn(): MediaUrlGenerator => new MediaUrlGenerator());
$container->set(
    MediaRepository::class,
    static fn(Container $container): MediaRepository => new MediaRepository(
        $container->get(SafePathResolver::class),
        $container->get(ConfigurationRepository::class),
        $container->get(MediaInspector::class),
    ),
);
$container->set(
    MediaVariantService::class,
    static fn(Container $container): MediaVariantService => new MediaVariantService(
        $container->get(ConfigurationRepository::class),
        $container->get(RasterImageProcessor::class),
        $container->get(SafePathResolver::class),
        $container->get(AtomicFileWriter::class),
        $container->get(FileLockManager::class),
    ),
);
$container->set(
    PublicMediaController::class,
    static fn(Container $container): PublicMediaController => new PublicMediaController(
        $container->get(MediaRepository::class),
        $container->get(MediaVariantService::class),
    ),
);
$container->set(
    PasswordResetRepository::class,
    static fn(Container $container): PasswordResetRepository => new PasswordResetRepository(
        $container->get(Database::class)->connection(),
        $container->get(UserRepository::class),
    ),
);
$container->set(
    Mailer::class,
    static function (Container $container): Mailer {
        $environment = $container->get(Environment::class);
        if ($environment->get('MAIL_TRANSPORT', 'smtp') !== 'smtp') {
            throw new MailException('Only the smtp mail transport is supported.');
        }

        return new SmtpMailer(
            $environment->get('MAIL_HOST', '127.0.0.1'),
            $environment->integer('MAIL_PORT', 1025),
            $environment->get('MAIL_ENCRYPTION', 'none'),
            $environment->get('MAIL_USERNAME', ''),
            $environment->get('MAIL_PASSWORD', ''),
            $environment->get('MAIL_FROM_ADDRESS'),
            $environment->get('MAIL_FROM_NAME', 'FlatFile CMS'),
        );
    },
);
$container->set(
    PasswordResetService::class,
    static fn(Container $container): PasswordResetService => new PasswordResetService(
        $container->get(UserRepository::class),
        $container->get(PasswordResetRepository::class),
        $container->get(PasswordHasher::class),
        $container->get(PasswordPolicy::class),
        new RateLimiter(
            $container->get(Database::class)->connection(),
            $container->get(Environment::class)->get('APP_SECRET'),
            $container->get(Environment::class)->integer('AUTH_RESET_MAX_ATTEMPTS', 3),
            $container->get(Environment::class)->integer('AUTH_RESET_WINDOW_SECONDS', 3600),
        ),
        $container->get(Mailer::class),
        $container->get(ConfigurationRepository::class),
        $container->get(AuditLogger::class),
        $container->get(Environment::class)->integer('AUTH_PASSWORD_RESET_TTL', 3600),
    ),
);
$container->set(
    PasswordResetController::class,
    static fn(Container $container): PasswordResetController => new PasswordResetController(
        $container->get(CsrfTokenManager::class),
        $container->get(PasswordResetService::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
    ),
);
$container->set(
    ContentFileIndex::class,
    static fn(Container $container): ContentFileIndex => new ContentFileIndex($container->get(SafePathResolver::class)),
);
$container->set(
    PageRepository::class,
    static fn(Container $container): PageRepository => new PageRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
        $container->get(ContentFileIndex::class),
    ),
);
$container->set(
    DirectoryOperator::class,
    static fn(Container $container): DirectoryOperator => new DirectoryOperator(
        $container->get(SafePathResolver::class),
    ),
);
$container->set(LocalizedDataResolver::class, static fn(): LocalizedDataResolver => new LocalizedDataResolver());
$container->set(
    CollectionRepository::class,
    static fn(Container $container): CollectionRepository => new CollectionRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
        $container->get(ContentFileIndex::class),
    ),
);
$container->set(
    CollectionService::class,
    static fn(Container $container): CollectionService => new CollectionService(
        $container->get(LocalizedDataResolver::class),
    ),
);
$container->set(
    CollectionManager::class,
    static fn(Container $container): CollectionManager => new CollectionManager(
        $container->get(YamlFileRepository::class),
        $container->get(CollectionRepository::class),
        $container->get(PageRepository::class),
        $container->get(LayoutRegistry::class),
        $container->get(FileLockManager::class),
    ),
);
$container->set(
    FieldTypeRegistry::class,
    static fn(Container $container): FieldTypeRegistry => BuiltinFieldTypes::create(
        $container->get(SafePathResolver::class),
    ),
);
$container->set(
    BlockRegistry::class,
    static fn(Container $container): BlockRegistry => new BlockRegistry(
        $container->get(Environment::class)->projectRoot(),
        $container->get(YamlParser::class),
        $container->get(FieldTypeRegistry::class),
        $container->get(YamlFileCache::class),
    ),
);
$container->set(
    BlockValidator::class,
    static fn(Container $container): BlockValidator => new BlockValidator(
        $container->get(FieldTypeRegistry::class),
    ),
);
$container->set(
    BlockProcessor::class,
    static fn(Container $container): BlockProcessor => new BlockProcessor(
        $container->get(BlockRegistry::class),
        $container->get(BlockValidator::class),
    ),
);
$container->set(
    PageBlockManager::class,
    static fn(Container $container): PageBlockManager => new PageBlockManager(
        $container->get(YamlFileRepository::class),
        $container->get(PageRepository::class),
        $container->get(BlockRegistry::class),
        $container->get(BlockValidator::class),
        $container->get(BlockProcessor::class),
        $container->get(FileLockManager::class),
    ),
);
$container->set(
    MediaManager::class,
    static fn(Container $container): MediaManager => new MediaManager(
        $container->get(MediaRepository::class),
        $container->get(PageBlockManager::class),
        $container->get(ConfigurationRepository::class),
        $container->get(MediaInspector::class),
        $container->get(RasterImageProcessor::class),
        $container->get(SafePathResolver::class),
        $container->get(AtomicFileWriter::class),
        $container->get(FileLockManager::class),
    ),
);
$container->set(BlockFormDataMapper::class, static fn(): BlockFormDataMapper => new BlockFormDataMapper());
$container->set(BlockFormRenderer::class, static fn(): BlockFormRenderer => new BlockFormRenderer());
$container->set(
    PageManager::class,
    static fn(Container $container): PageManager => new PageManager(
        $container->get(YamlFileRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
        $container->get(BlockProcessor::class),
        $container->get(LayoutRegistry::class),
        $container->get(DirectoryOperator::class),
        $container->get(FileLockManager::class),
        $container->get(ContentFileIndex::class),
    ),
);
$container->set(
    AdminPageController::class,
    static fn(Container $container): AdminPageController => new AdminPageController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(LanguageRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
        $container->get(PageManager::class),
        $container->get(LayoutRegistry::class),
        $container->get(ConfigurationRepository::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    AdminCollectionController::class,
    static fn(Container $container): AdminCollectionController => new AdminCollectionController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(LanguageRepository::class),
        $container->get(CollectionManager::class),
        $container->get(LayoutRegistry::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    AdminUserManager::class,
    static fn(Container $container): AdminUserManager => new AdminUserManager(
        $container->get(UserRepository::class),
        $container->get(PasswordPolicy::class),
        $container->get(PasswordHasher::class),
    ),
);
$container->set(
    AdminUserController::class,
    static fn(Container $container): AdminUserController => new AdminUserController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(UserRepository::class),
        $container->get(AdminUserManager::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    AdminPageBuilderController::class,
    static fn(Container $container): AdminPageBuilderController => new AdminPageBuilderController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(LanguageRepository::class),
        $container->get(PageBlockManager::class),
        $container->get(BlockRegistry::class),
        $container->get(BlockFormDataMapper::class),
        $container->get(BlockFormRenderer::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    AdminMediaController::class,
    static fn(Container $container): AdminMediaController => new AdminMediaController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(PageBlockManager::class),
        $container->get(MediaRepository::class),
        $container->get(MediaManager::class),
        $container->get(MediaUrlGenerator::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    NavigationRepository::class,
    static fn(Container $container): NavigationRepository => new NavigationRepository(
        $container->get(YamlFileRepository::class),
        $container->get(SafePathResolver::class),
        $container->get(LocalizedDataResolver::class),
    ),
);
$container->set(
    NavigationManager::class,
    static fn(Container $container): NavigationManager => new NavigationManager(
        $container->get(NavigationRepository::class),
        $container->get(LanguageRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
    ),
);
$container->set(
    ConfigurationManager::class,
    static fn(Container $container): ConfigurationManager => new ConfigurationManager(
        $container->get(ConfigurationRepository::class),
        $container->get(LayoutRegistry::class),
    ),
);
$container->set(
    AdminSettingsController::class,
    static fn(Container $container): AdminSettingsController => new AdminSettingsController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(LanguageRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
        $container->get(NavigationManager::class),
        $container->get(ConfigurationManager::class),
        $container->get(SiteTextRepository::class),
        $container->get(LayoutRegistry::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    AdminRedirectController::class,
    static fn(Container $container): AdminRedirectController => new AdminRedirectController(
        $container->get(Authenticator::class),
        $container->get(CsrfTokenManager::class),
        $container->get(RedirectRepository::class),
        $container->get(RedirectManager::class),
        $container->get(AdminView::class),
        $container->get(AdminLayout::class),
        $container->get(AuditLogger::class),
    ),
);
$container->set(
    SeoResolver::class,
    static fn(Container $container): SeoResolver => new SeoResolver(
        $container->get(LocalizedDataResolver::class),
    ),
);
$container->set(
    MediaOutputEnricher::class,
    static fn(Container $container): MediaOutputEnricher => new MediaOutputEnricher(
        $container->get(BlockRegistry::class),
        $container->get(MediaRepository::class),
        $container->get(MediaUrlGenerator::class),
    ),
);
$container->set(
    PageViewModelFactory::class,
    static fn(Container $container): PageViewModelFactory => new PageViewModelFactory(
        $container->get(BlockProcessor::class),
        $container->get(SeoResolver::class),
        $container->get(MediaOutputEnricher::class),
    ),
);
$container->set(
    CollectionViewModelFactory::class,
    static fn(Container $container): CollectionViewModelFactory => new CollectionViewModelFactory(
        $container->get(LocalizedDataResolver::class),
        $container->get(SeoResolver::class),
    ),
);
$container->set(
    PageSerializer::class,
    static fn(Container $container): PageSerializer => new PageSerializer(
        $container->get(PageViewModelFactory::class),
    ),
);
$container->set(
    CollectionSerializer::class,
    static fn(Container $container): CollectionSerializer => new CollectionSerializer(
        $container->get(CollectionViewModelFactory::class),
    ),
);
$container->set(ApiResponseFactory::class, static fn(): ApiResponseFactory => new ApiResponseFactory());
$container->set(
    PublicApiController::class,
    static fn(Container $container): PublicApiController => new PublicApiController(
        $container->get(LanguageRepository::class),
        $container->get(ConfigurationRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
        $container->get(CollectionService::class),
        $container->get(NavigationRepository::class),
        $container->get(LocalizedDataResolver::class),
        $container->get(PageSerializer::class),
        $container->get(CollectionSerializer::class),
        $container->get(ApiResponseFactory::class),
    ),
);
$container->set(OutputBuffer::class, static fn(): OutputBuffer => new OutputBuffer());
$container->set(MarkdownRenderer::class, static fn(): MarkdownRenderer => new MarkdownRenderer());
$container->set(
    LayoutRegistry::class,
    static fn(Container $container): LayoutRegistry => new LayoutRegistry(
        $container->get(Environment::class)->projectRoot(),
    ),
);
$container->set(
    PartialRegistry::class,
    static fn(Container $container): PartialRegistry => new PartialRegistry(
        $container->get(Environment::class)->projectRoot(),
    ),
);
$container->set(
    PartialRenderer::class,
    static fn(Container $container): PartialRenderer => new PartialRenderer(
        $container->get(PartialRegistry::class),
        $container->get(OutputBuffer::class),
    ),
);
$container->set(
    BlockRenderer::class,
    static fn(Container $container): BlockRenderer => new BlockRenderer(
        $container->get(BlockRegistry::class),
        $container->get(OutputBuffer::class),
    ),
);
$container->set(
    LayoutRenderer::class,
    static fn(Container $container): LayoutRenderer => new LayoutRenderer(
        $container->get(LayoutRegistry::class),
        $container->get(OutputBuffer::class),
    ),
);
$container->set(
    AssetPublisher::class,
    static fn(Container $container): AssetPublisher => new AssetPublisher(
        $container->get(Environment::class)->projectRoot(),
    ),
);
$container->set(
    AssetCollector::class,
    static fn(Container $container): AssetCollector => new AssetCollector(
        $container->get(BlockRegistry::class),
        $container->get(AssetPublisher::class),
    ),
);
$container->set(
    PageRenderer::class,
    static fn(Container $container): PageRenderer => new PageRenderer(
        $container->get(BlockRenderer::class),
        $container->get(LayoutRenderer::class),
        $container->get(AssetCollector::class),
        $container->get(MarkdownRenderer::class),
        $container->get(PartialRenderer::class),
        $container->get(MediaRepository::class),
        $container->get(MediaUrlGenerator::class),
    ),
);
$container->set(
    CollectionRenderer::class,
    static fn(Container $container): CollectionRenderer => new CollectionRenderer(
        $container->get(LayoutRegistry::class),
        $container->get(OutputBuffer::class),
        $container->get(MarkdownRenderer::class),
        $container->get(PartialRenderer::class),
    ),
);
$container->set(HtmlResponseFactory::class, static fn(): HtmlResponseFactory => new HtmlResponseFactory());
$container->set(
    SiteController::class,
    static fn(Container $container): SiteController => new SiteController(
        $container->get(LanguageRepository::class),
        $container->get(ConfigurationRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
        $container->get(CollectionService::class),
        $container->get(NavigationRepository::class),
        $container->get(PageViewModelFactory::class),
        $container->get(CollectionViewModelFactory::class),
        $container->get(PageRenderer::class),
        $container->get(CollectionRenderer::class),
        $container->get(HtmlResponseFactory::class),
    ),
);
$container->set(
    SitemapController::class,
    static fn(Container $container): SitemapController => new SitemapController(
        $container->get(LanguageRepository::class),
        $container->get(ConfigurationRepository::class),
        $container->get(PageRepository::class),
        $container->get(CollectionRepository::class),
    ),
);
$container->set(
    SiteTextController::class,
    static fn(Container $container): SiteTextController => new SiteTextController(
        $container->get(SiteTextRepository::class),
    ),
);
$container->set(Router::class, static function (Container $container) use ($projectRoot): Router {
    $router = new Router();
    $registerRoutes = require "{$projectRoot}/config/routes.php";
    if (!is_callable($registerRoutes)) {
        throw new RuntimeException('Route configuration must return a callable.');
    }

    $registerRoutes($router, $container);

    return $router;
});
$container->set(ErrorHandler::class, static fn(Container $container): ErrorHandler => new ErrorHandler(
    debug: $container->get(Environment::class)->debug(),
    logger: $container->get(LoggerInterface::class),
));

return new Application(
    router: $container->get(Router::class),
    errorHandler: $container->get(ErrorHandler::class),
    trustedProxies: $container->get(TrustedProxyResolver::class),
);
