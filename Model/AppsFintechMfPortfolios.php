<?php

namespace Apps\Fintech\Packages\Mf\Portfolios\Model;

use Apps\Fintech\Packages\Mf\Investments\Model\AppsFintechMfInvestments;
use Apps\Fintech\Packages\Mf\Transactions\Model\AppsFintechMfTransactions;
use System\Base\BaseModel;

class AppsFintechMfPortfolios extends BaseModel
{
    protected $modelRelations = [];

    public $id;

    public $name;

    public $description;

    public $account_id;

    public $user_id;

    public $invested_amount;

    public $return_amount;

    public $sold_amount;

    public $profit_loss;

    public $total_value;

    public $xirr;

    public $strategy_ids;

    public $allocation;

    public $status;

    public $timeline;

    public $recalculate_timeline;

    public function initialize()
    {
        $this->modelRelations['investments']['relationObj'] = $this->hasMany(
            'id',
            AppsFintechMfInvestments::class,
            'portfolio_id',
            [
                'alias'         => 'investments'
            ]
        );

        $this->modelRelations['transactions']['relationObj'] = $this->hasMany(
            'id',
            AppsFintechMfTransactions::class,
            'portfolio_id',
            [
                'alias'         => 'transactions'
            ]
        );

        parent::initialize();
    }

    public function getModelRelations()
    {
        if (count($this->modelRelations) === 0) {
            $this->initialize();
        }

        return $this->modelRelations;
    }
}