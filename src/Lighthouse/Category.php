<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Lighthouse;

enum Category: string
{
    case Performance   = 'performance';
    case Accessibility = 'accessibility';
    case BestPractices = 'best-practices';
    case Seo           = 'seo';
}
