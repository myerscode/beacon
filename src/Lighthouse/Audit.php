<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Lighthouse;

enum Audit: string
{
    // Performance metrics
    case FirstContentfulPaint   = 'first-contentful-paint';
    case LargestContentfulPaint = 'largest-contentful-paint';
    case SpeedIndex             = 'speed-index';
    case TotalBlockingTime      = 'total-blocking-time';
    case CumulativeLayoutShift  = 'cumulative-layout-shift';
    case InteractionToNextPaint = 'interaction-to-next-paint';
    case Interactive            = 'interactive';
    case MaxPotentialFid        = 'max-potential-fid';
    case ServerResponseTime     = 'server-response-time';

    // Performance diagnostics
    case MainthreadWorkBreakdown = 'mainthread-work-breakdown';
    case BootupTime              = 'bootup-time';
    case TotalByteWeight         = 'total-byte-weight';
    case UnminifiedCss           = 'unminified-css';
    case UnminifiedJavascript    = 'unminified-javascript';
    case UnusedCssRules          = 'unused-css-rules';
    case UnusedJavascript        = 'unused-javascript';
    case UnsizedImages           = 'unsized-images';
    case Redirects               = 'redirects';
    case LongTasks               = 'long-tasks';
    case ErrorsInConsole         = 'errors-in-console';

    // Accessibility
    case ColorContrast  = 'color-contrast';
    case DocumentTitle  = 'document-title';
    case HtmlHasLang    = 'html-has-lang';
    case HtmlLangValid  = 'html-lang-valid';
    case ImageAlt       = 'image-alt';
    case Label          = 'label';
    case LinkName       = 'link-name';
    case MetaViewport   = 'meta-viewport';
    case ButtonName     = 'button-name';
    case AriaRoles      = 'aria-roles';
    case HeadingOrder   = 'heading-order';
    case Bypass         = 'bypass';
    case TabIndex       = 'tabindex';
    case VideoCaption   = 'video-caption';

    // Best practices
    case IsOnHttps           = 'is-on-https';
    case RedirectsHttp       = 'redirects-http';
    case Deprecations        = 'deprecations';
    case ThirdPartyCookies   = 'third-party-cookies';
    case Doctype             = 'doctype';
    case Charset             = 'charset';
    case ImageAspectRatio    = 'image-aspect-ratio';
    case ImageSizeResponsive = 'image-size-responsive';

    // SEO
    case MetaDescription  = 'meta-description';
    case HttpStatusCode   = 'http-status-code';
    case LinkText         = 'link-text';
    case CrawlableAnchors = 'crawlable-anchors';
    case IsCrawlable      = 'is-crawlable';
    case RobotsTxt        = 'robots-txt';
    case Hreflang         = 'hreflang';
    case Canonical        = 'canonical';
}
