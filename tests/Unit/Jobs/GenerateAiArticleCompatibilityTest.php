<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateAiArticle;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class GenerateAiArticleCompatibilityTest extends TestCase
{
    public function test_jobs_serialized_before_dispatch_image_existed_keep_the_default_value(): void
    {
        $class = GenerateAiArticle::class;
        $legacyPayload = sprintf(
            'O:%d:"%s":1:{s:6:"taskId";i:123;}',
            strlen($class),
            $class,
        );

        $job = unserialize($legacyPayload);
        $dispatchImage = new ReflectionProperty($job, 'dispatchImage');

        $this->assertInstanceOf(GenerateAiArticle::class, $job);
        $this->assertSame('123', $job->uniqueId());
        $this->assertTrue($dispatchImage->getValue($job));
    }
}
