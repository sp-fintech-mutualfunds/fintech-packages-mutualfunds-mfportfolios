<?php

namespace Apps\Fintech\Packages\Mf\Portfolios;

use Apps\Fintech\Packages\Mf\Portfolios\Model\AppsFintechMfPortfolios;
use Apps\Fintech\Packages\Mf\Transactions\MfTransactions;
use System\Base\BasePackage;

class MfPortfolios extends BasePackage
{
    protected $modelToUse = AppsFintechMfPortfolios::class;

    protected $packageName = 'mfportfolios';

    public $mfportfolios;

    public function getPortfolioById(int $id)
    {
        $this->ffStore = $this->ff->store($this->ffStoreToUse);

        $this->setFFRelations(true);

        $this->getFirst('id', $id);

        if ($this->model) {
            $portfolio = $this->model->toArray();

            $portfolio['transactions'] = [];
            if ($this->model->gettransactions()) {
                $portfolio['transactions'] = $this->model->getsecurity()->toArray();
            }
        } else {
            if ($this->ffData) {
                $portfolio = $this->jsonData($this->ffData, true);
            }
        }

        if ($portfolio) {
            return $portfolio;
        }

        return false;
    }

    public function addPortfolio($data)
    {
        if ($data['user_id'] == 0) {
            $this->addResponse('User not set', 1);

            return;
        }

        $data['account_id'] = $this->access->auth->account()['id'];
        $data['invested_amount'] = 0.00;
        $data['total_value'] = 0.00;
        $data['profit_loss'] = 0.00;

        if ($this->add($data)) {
            $this->addResponse('User Added');
        } else {
            $this->addResponse('Error Adding User', 1);
        }
    }

    public function updatePortfolio($data)
    {
        $portfolio = $this->getById((int) $data['id']);

        if ($portfolio) {
            $data = array_merge($portfolio, $data);

            if ($this->update($data)) {
                $this->addResponse('User updated');

                return;
            }
        }

        $this->addResponse('Error', 1);
    }

    public function removePortfolio($data)
    {
        $mfportfolios = $this->getById($data['id']);

        if ($mfportfolios) {
            if ($this->remove($mfportfolios['id'])) {
                $this->addResponse('Success');
                return;
            }
        }

        $this->addResponse('Error', 1);
    }
}