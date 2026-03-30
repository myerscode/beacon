<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Lighthouse;

enum Audit: string
{
    case AriaRoles      = 'aria-roles';
    case BootupTime              = 'bootup-time';
    case ButtonName     = 'button-name';
    case Bypass         = 'bypass';
    case Canonical        = 'canonical';
    case Charset             = 'charset';

    // Accessibility
    case ColorContrast  = 'color-contrast';
    case CrawlableAnchors = 'crawlable-anchors';
    case CumulativeLayoutShift  = 'cumulative-layout-shift';
    case Deprecations        = 'deprecations';
    case Doctype             = 'doctype';
    case DocumentTitle  = 'document-title';
    case ErrorsInConsole         = 'errors-in-console';
    // Performance metrics
    case FirstContentfulPaint   = 'first-contentful-paint';
    case HeadingOrder   = 'heading-order';
    case Hreflang         = 'hreflang';
    case HtmlHasLang    = 'html-has-lang';
    case HtmlLangValid  = 'html-lang-valid';
    case HttpStatusCode   = 'http-status-code';
    case ImageAlt       = 'image-alt';
    case ImageAspectRatio    = 'image-aspect-ratio';
    case ImageSizeResponsive = 'image-size-responsive';
    case InteractionToNextPaint = 'interaction-to-next-paint';
    case Interactive            = 'interactive';
    case IsCrawlable      = 'is-crawlable';

    // Best practices
    case IsOnHttps           = 'is-on-https';
    case Label          = 'label';
    case LargestContentfulPaint = 'largest-contentful-paint';
    case LinkName       = 'link-name';
    case LinkText         = 'link-text';
    case LongTasks               = 'long-tasks';

    // Performance diagnostics
    case MainthreadWorkBreakdown = 'mainthread-work-breakdown';
    case MaxPotentialFid        = 'max-potential-fid';

    // SEO
    case MetaDescription  = 'meta-description';
    case MetaViewport   = 'meta-viewport';
    case Redirects               = 'redirects';
    case RedirectsHttp       = 'redirects-http';
    case RobotsTxt        = 'robots-txt';
    case ServerResponseTime     = 'server-response-time';
    case SpeedIndex             = 'speed-index';
    case TabIndex       = 'tabindex';
    case ThirdPartyCookies   = 'third-party-cookies';
    case TotalBlockingTime      = 'total-blocking-time';
    case TotalByteWeight         = 'total-byte-weight';
    case UnminifiedCss           = 'unminified-css';
    case UnminifiedJavascript    = 'unminified-javascript';
    case UnsizedImages           = 'unsized-images';
    case UnusedCssRules          = 'unused-css-rules';
    case UnusedJavascript        = 'unused-javascript';
    case VideoCaption   = 'video-caption';
}
