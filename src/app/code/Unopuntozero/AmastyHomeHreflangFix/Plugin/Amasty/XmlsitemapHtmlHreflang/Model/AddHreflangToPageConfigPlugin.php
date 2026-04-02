<?php

declare(strict_types=1);

namespace Unopuntozero\AmastyHomeHreflangFix\Plugin\Amasty\XmlsitemapHtmlHreflang\Model;

use Amasty\XmlsitemapHtmlHreflang\Model\AddHreflangToPageConfig;
use Magento\Framework\View\Page\Config;

class AddHreflangToPageConfigPlugin
{
    public function aroundExecute(
        AddHreflangToPageConfig $subject,
        callable $proceed,
        string $entityCode,
        int $entityId,
        Config $pageConfig,
        ?string $customEntityId = null
    ): void {
        $proceed($entityCode, $entityId, $pageConfig, $customEntityId);
    }
}
