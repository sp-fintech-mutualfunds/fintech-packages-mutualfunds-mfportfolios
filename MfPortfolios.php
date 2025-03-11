<?php

namespace Apps\Fintech\Packages\Mf\Portfolios;

use System\Base\BasePackage;

class MfPortfolios extends BasePackage
{
    //protected $modelToUse = ::class;

    protected $packageName = 'mfportfolios';

    public $mfportfolios;

    public function getMfPortfoliosById($id)
    {
        $mfportfolios = $this->getById($id);

        if ($mfportfolios) {
            //
            $this->addResponse('Success');

            return;
        }

        $this->addResponse('Error', 1);
    }

    public function addMfPortfolios($data)
    {
        //
    }

    public function updateMfPortfolios($data)
    {
        $mfportfolios = $this->getById($id);

        if ($mfportfolios) {
            //
            $this->addResponse('Success');

            return;
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