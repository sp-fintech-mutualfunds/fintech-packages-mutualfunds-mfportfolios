<?php

namespace Apps\Fintech\Packages\Mf\Portfolios\Model;

use System\Base\BaseModel;

class AppsFintechMfPortfoliosStrategies extends BaseModel
{
    public $id;

    public $account_id;

    public $user_id;

    public $portfolio_id;

    public $strategy_id;

    public $scheme_id;

    public $date;

    public $units_bought;

    public $units_sold;

    public $nav;

    public $amount;

    public $details;

}