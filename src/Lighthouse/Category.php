<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Lighthouse;

enum Category: string
{
    case Accessibility = 'accessibility';
    case BestPractices = 'best-practices';
    case Performance   = 'performance';
    case Seo           = 'seo';
}
