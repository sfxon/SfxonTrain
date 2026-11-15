<?php declare(strict_types=1);

namespace OCA\SfxonTrain\Service;

final class TrainStatus
{
    // TrainUser::status
    public const USER_ASSIGNED = 1;
    public const USER_IN_PROGRESS = 2;
    public const USER_COMPLETED = 3;

    // TrainUserRun::status
    public const RUN_IN_PROGRESS = 0;
    public const RUN_PASSED = 1;
    public const RUN_FAILED = 2;
}