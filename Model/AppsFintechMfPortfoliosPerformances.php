<?php

namespace Apps\Fintech\Packages\Mf\Portfolios\Model;

use System\Base\BaseModel;

class AppsFintechMfPortfoliosPerformances extends BaseModel
{
    public $id;

    public $portfolio_id;

    public $performances_chunks;
}