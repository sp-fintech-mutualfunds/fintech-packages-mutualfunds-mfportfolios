<?php

namespace Apps\Fintech\Packages\Mf\Portfolios;

use Apps\Fintech\Packages\Mf\Portfolios\Model\AppsFintechMfPortfolios;
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

            return $portfolio;
        } else {
            if ($this->ffData) {
                $this->ffData = $this->jsonData($this->ffData, true);

                return $this->ffData;
            }
        }

        return null;
    }

    public function addPortfolio($data)
    {
        $data['account_id'] = $this->access->auth->account()['id'];
        $data['equity_balance'] = 0.00;
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

    public function removeMfPortfolios($data)
    {
        $mfportfolios = $this->getById($id);

        if ($mfportfolios) {
            //
            $this->addResponse('Success');

            return;
        }

        $this->addResponse('Error', 1);
    }
}