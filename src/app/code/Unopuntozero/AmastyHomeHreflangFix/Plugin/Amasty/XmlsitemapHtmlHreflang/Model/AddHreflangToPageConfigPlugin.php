<?php

declare(strict_types=1);

namespace Unopuntozero\AmastyHomeHreflangFix\Plugin\Amasty\XmlsitemapHtmlHreflang\Model;

use Amasty\XmlsitemapHtmlHreflang\Model\AddHreflangToPageConfig;
use Amasty\XmlsitemapHtmlHreflang\Model\ConfigProvider;
use Amasty\XmlSitemap\Model\Sitemap\HreflangProvider;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Page\Config;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AddHreflangToPageConfigPlugin
{
    private const XML_PATH_CMS_HOME_PAGE = 'web/default/cms_home_page';
    private const CMS_ENTITY_CODE = 'cms-page';

    public function __construct(
        private readonly HreflangProvider $hreflangProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly ConfigProvider $configProvider,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundExecute(
        AddHreflangToPageConfig $subject,
        callable $proceed,
        string $entityCode,
        int $entityId,
        Config $pageConfig,
        ?string $customEntityId = null
    ): void {
        if (!$this->shouldHandleHomepageCms($entityCode, $entityId)) {
            $proceed($entityCode, $entityId, $pageConfig, $customEntityId);
            return;
        }

        if (!$entityId || !$this->configProvider->isAddHreflangToEntityPageHead($entityCode)) {
            $proceed($entityCode, $entityId, $pageConfig, $customEntityId);
            return;
        }

        $storeId = (int)$this->storeManager->getStore()->getId();

        $hreflangItems = $this->hreflangProvider->getData(
            $storeId,
            $entityCode,
            [$entityId]
        );

        $hreflangs = [];

        if ($customEntityId !== null && isset($hreflangItems[$customEntityId]) && is_array($hreflangItems[$customEntityId])) {
            $hreflangs = $hreflangItems[$customEntityId];
        }

        if (!$hreflangs && isset($hreflangItems[$entityId]) && is_array($hreflangItems[$entityId])) {
            $hreflangs = $hreflangItems[$entityId];
        }

        if (!$hreflangs || count($hreflangs) <= 1) {
            $proceed($entityCode, $entityId, $pageConfig, $customEntityId);
            return;
        }

        $baseUrlMap = $this->buildHreflangToBaseUrlMap();

        foreach ($hreflangs as $hreflang) {
            if (!isset($hreflang['attributes']) || !is_array($hreflang['attributes'])) {
                continue;
            }

            $attributes = $hreflang['attributes'];
            $hreflangCode = strtolower((string)($attributes['hreflang'] ?? ''));
            $href = (string)($attributes['href'] ?? '');

            if ($hreflangCode !== '' && isset($baseUrlMap[$hreflangCode])) {
                $href = $baseUrlMap[$hreflangCode];
            }

            if ($href === '') {
                continue;
            }

            $hreflang['attributes']['href'] = $href;
            $finalHref = $hreflang['attributes']['href'];
            unset($hreflang['attributes']['href']);

            $pageConfig->addRemotePageAsset(
                $finalHref,
                'hreflang',
                $hreflang
            );
        }
    }

    private function shouldHandleHomepageCms(string $entityCode, int $entityId): bool
    {
        if ($entityCode !== self::CMS_ENTITY_CODE || !$entityId) {
            return false;
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();

            $homeIdentifier = (string)$this->scopeConfig->getValue(
                self::XML_PATH_CMS_HOME_PAGE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            if ($homeIdentifier === '') {
                return false;
            }

            $currentPage = $this->pageRepository->getById($entityId);
            $currentIdentifier = (string)$currentPage->getIdentifier();

            return $currentIdentifier === $homeIdentifier;
        } catch (NoSuchEntityException $e) {
            $this->logger->warning(
                '[Unopuntozero_AmastyHomeHreflangFix] CMS page not found: ' . $e->getMessage()
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                '[Unopuntozero_AmastyHomeHreflangFix] Error while resolving homepage CMS: ' . $e->getMessage()
            );
        }

        return false;
    }

    private function buildHreflangToBaseUrlMap(): array
    {
        $map = [];

        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->isActive()) {
                continue;
            }

            $storeId = (int)$store->getId();
            $baseUrl = rtrim($store->getBaseUrl(), '/') . '/';

            $locale = (string)$this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            $locale = strtolower(str_replace('_', '-', $locale));
            $lang2 = substr($locale, 0, 2);

            if ($lang2 !== '') {
                $map[$lang2] = $baseUrl;
            }

            if ($locale !== '') {
                $map[$locale] = $baseUrl;
            }
        }

        if (isset($map['en'])) {
            $map['x-default'] = $map['en'];
        }

        return $map;
    }
}
