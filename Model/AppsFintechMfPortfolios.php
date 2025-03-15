<?php

namespace Apps\Fintech\Packages\Mf\Portfolios\Model;

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

    public $strategy_ids;

    public $invested_amount;

    public $total_value;

    public $profit_loss;

    public $timeline;

    public function initialize()
    {
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