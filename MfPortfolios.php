<?php

namespace Apps\Fintech\Packages\Mf\Portfolios;

use Apps\Fintech\Packages\Mf\Categories\MfCategories;
use Apps\Fintech\Packages\Mf\Investments\MfInvestments;
use Apps\Fintech\Packages\Mf\Portfolios\Model\AppsFintechMfPortfolios;
use Apps\Fintech\Packages\Mf\Portfoliostimeline\MfPortfoliostimeline;
use Apps\Fintech\Packages\Mf\Schemes\MfSchemes;
use Apps\Fintech\Packages\Mf\Transactions\MfTransactions;
use System\Base\BasePackage;

class MfPortfolios extends BasePackage
{
    protected $modelToUse = AppsFintechMfPortfolios::class;

    protected $packageName = 'mfportfolios';

    public $mfportfolios;

    protected $portfolio;

    protected $today;

    protected $transactionsPackage;

    protected $investmentsPackage;

    protected $schemesPackage;

    protected $scheme;

    protected $investments = [];

    protected $portfolioXirrDatesArr = [];

    protected $portfolioXirrAmountsArr = [];

    public function init()
    {
        $this->today = (\Carbon\Carbon::now(new \DateTimeZone('Asia/Kolkata')))->toDateString();

        $this->transactionsPackage = $this->usepackage(MfTransactions::class);

        $this->investmentsPackage = $this->usepackage(MfInvestments::class);

        $this->schemesPackage = $this->usepackage(MfSchemes::class);

        parent::init();

        return $this;
    }

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
            if ($portfolio['transactions'] && count($portfolio['transactions']) > 0) {
                $transactions = $portfolio['transactions'];

                $portfolio['transactions'] = [];

                foreach ($transactions as $transaction) {
                    $portfolio['transactions'][$transaction['id']] = $transaction;
                }
            }

            if ($portfolio['investments'] && count($portfolio['investments']) > 0) {
                $investments = $portfolio['investments'];

                $portfolio['investments'] = [];

                foreach ($investments as $investment) {
                    $portfolio['investments'][$investment['amfi_code']] = $investment;
                }
            }

            if (isset($portfolio['timeline'])) {
                unset($portfolio['timeline']);
            }

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

        if (!checkCtype($data['name'], 'alnum', ['_'])) {
            $this->addResponse('Name cannot have special chars or numbers.', 1);

            return false;
        }

        $data['account_id'] = $this->access->auth->account()['id'];
        $data['invested_amount'] = 0.00;
        $data['return_amount'] = 0.00;
        $data['sold_amount'] = 0.00;
        $data['profit_loss'] = 0.00;
        $data['total_value'] = 0.00;
        $data['xir'] = 0;
        $data['status'] = 'neutral';
        $data['start_date'] = $this->today;

        if ($this->add($data)) {
            $portfoliotimelinePackage = $this->usePackage(MfPortfoliostimeline::class);

            $portfoliotimeline['portfolio_id'] = $this->packagesData->last['id'];
            $portfoliotimeline['snapshots'] = $this->helper->encode([]);
            $portfoliotimeline['performance_chunks'] = $this->helper->encode([]);

            $portfoliotimelinePackage->add($portfoliotimeline);

            $this->addResponse('Portfolio Added');
        } else {
            $this->addResponse('Error Adding Portfolio', 1);
        }
    }

    public function updatePortfolio($data)
    {
        $portfolio = $this->getById((int) $data['id']);

        if (!checkCtype($data['name'], 'alnum', ['_'])) {
            $this->addResponse('Name cannot have special chars or numbers.', 1);

            return false;
        }

        if ($portfolio) {
            $data = array_merge($portfolio, $data);

            if ($this->update($data)) {
                $this->addResponse('Portfolio updated');

                return;
            }
        }

        $this->addResponse('Error', 1);
    }

    public function clonePortfolio($data)
    {
        $mfportfolios = $this->getPortfolioById($data['id'], true);

        $mfportfolios['name'] = $mfportfolios['name'] . '_clone_' . str_replace(' ', '_', (\Carbon\Carbon::now(new \DateTimeZone('Asia/Kolkata')))->toDateTimeString());

        unset($mfportfolios['id']);
        // trace([$mfportfolios['transactions']]);
        if ($this->add($mfportfolios)) {
            $newPortfolioId = $this->packagesData->last['id'];

            if ($mfportfolios['timeline']) {
                unset($mfportfolios['timeline']['id']);

                $portfoliotimelinePackage = $this->usePackage(MfPortfoliostimeline::class);

                $portfoliotimeline['portfolio_id'] = $newPortfolioId;

                if (isset($mfportfolios['timeline']['snapshots'])) {
                    $portfoliotimeline['snapshots'] = $mfportfolios['timeline']['snapshots'];
                } else {
                    $portfoliotimeline['snapshots'] = $this->helper->encode([]);
                }

                $portfoliotimelinePackage->add($portfoliotimeline);
            }

            if ($mfportfolios['transactions'] && count($mfportfolios['transactions']) > 0) {
                foreach ($mfportfolios['transactions'] as $transaction) {
                    if ($transaction['type'] === 'buy') {
                        $newTransaction['portfolio_id'] = $newPortfolioId;
                        $newTransaction['date'] = $transaction['date'];
                        $newTransaction['amc_id'] = $transaction['amc_id'];
                        $newTransaction['amfi_code'] = $transaction['amfi_code'];
                        $newTransaction['amount'] = $transaction['amount'];
                        $newTransaction['amc_transaction_id'] = $transaction['amc_transaction_id'];
                        $newTransaction['type'] = 'buy';
                        $newTransaction['details'] = $transaction['details'];
                    } else if ($transaction['type'] === 'sell') {
                        $newTransaction['portfolio_id'] = $newPortfolioId;
                        $newTransaction['date'] = $transaction['date'];
                        $newTransaction['amount'] = $transaction['amount'];
                        $newTransaction['sell_all'] = $transaction['sell_all'];
                        $newTransaction['amc_transaction_id'] = $transaction['amc_transaction_id'];
                        $newTransaction['type'] = 'sell';
                        $newTransaction['details'] = $transaction['details'];
                    }

                    $newTransaction['clone'] = true;

                    $this->transactionsPackage->addMfTransaction($newTransaction);
                }
            }

            if ($mfportfolios['investments'] && count($mfportfolios['investments']) > 0) {
                foreach ($mfportfolios['investments'] as $investment) {
                    $investment['portfolio_id'] = $newPortfolioId;
                    unset($investment['id']);

                    $this->investmentsPackage->add($investment);
                }
            }

            $this->recalculatePortfolio(['portfolio_id' => $newPortfolioId]);

            $this->addResponse('Portfolio Added');
        } else {
            $this->addResponse('Error Adding Portfolio', 1);
        }
    }

    public function removePortfolio($data)
    {
        $mfportfolios = $this->getPortfolioById($data['id']);

        if ($mfportfolios) {
            if ($this->remove($mfportfolios['id'])) {
                //Remove Timeline
                if (isset($mfportfolios['timeline']['id'])) {
                    $portfoliotimelinePackage = $this->usePackage(MfPortfoliostimeline::class);
                    $portfoliotimelinePackage->remove($mfportfolios['timeline']['id']);
                }
                //Remove Transactions
                if (isset($mfportfolios['transactions']) && count($mfportfolios['transactions']) > 0) {
                    foreach ($mfportfolios['transactions'] as $transaction) {
                        //
                    }
                }
                //Remove Transactions
                if (isset($mfportfolios['investments']) && count($mfportfolios['investments']) > 0) {
                    foreach ($mfportfolios['investments'] as $transaction) {
                        //
                    }
                }

                $this->addResponse('Success');

                return;
            }
        }

        $this->addResponse('Error', 1);
    }

    public function recalculatePortfolio($data, $viaAddUpdate = false, $timelineDate = null)
    {
        if (!$this->portfolio ||
            ($this->portfolio && $viaAddUpdate)
        ) {
            $this->portfolio = $this->getPortfolioById((int) $data['portfolio_id']);
        }

        if (!$this->portfolio) {
            $this->addResponse('Portfolio not found', 1);

            return false;
        }

        //Increase memory_limit to 1G as the process takes a bit of memory to process the scheme's navs array.
        if ((int) ini_get('memory_limit') < 1024) {
            ini_set('memory_limit', '1024M');
        }

        if ($this->portfolio['transactions'] && count($this->portfolio['transactions']) > 0) {
            $this->processTransactionsNumbers($data, $viaAddUpdate, $timelineDate);

            $this->processInvestmentNumbers($timelineDate);

            if ($timelineDate) {
                return $this->portfolio;
            } else {
                $this->update($this->portfolio);

                $returnArr =
                    [
                        'invested_amount' => $this->portfolio['invested_amount'],
                        'return_amount' => $this->portfolio['return_amount'],
                        'profit_loss' => $this->portfolio['profit_loss'],
                        'sold_amount' => $this->portfolio['sold_amount'],
                        'total_value' => $this->portfolio['total_value'],
                        'xir' => $this->portfolio['xirr']
                    ];

                $responseReturnArr = $returnArr;

                array_walk($responseReturnArr, function($value, $key) use (&$responseReturnArr) {
                    if ($key === 'invested_amount' ||
                        $key === 'return_amount' ||
                        $key === 'sold_amount' ||
                        $key === 'total_value' ||
                        $key === 'profit_loss'
                    ) {
                        if ($value) {
                            $responseReturnArr[$key] =
                                str_replace('EN_ ',
                                        '',
                                        (new \NumberFormatter('en_IN', \NumberFormatter::CURRENCY))
                                            ->formatCurrency($value, 'en_IN')
                            );
                        }
                    }
                });

                $this->addResponse('Recalculated', 0, $responseReturnArr);

                return $returnArr;
            }
        }
                    // trace([$this->portfolio]);

        if ($this->portfolio['investments'] && count($this->portfolio['investments']) > 0) {
            foreach ($this->portfolio['investments'] as $investment) {
                $this->investmentsPackage->remove($investment['id'])    ;
            }
        }

        $this->portfolio['investments'] = null;
        $this->portfolio['allocation'] = null;
        $this->portfolio['invested_amount'] = 0.00;
        $this->portfolio['return_amount'] = 0.00;
        $this->portfolio['sold_amount'] = 0.00;
        $this->portfolio['profit_loss'] = 0.00;
        $this->portfolio['total_value'] = 0.00;
        $this->portfolio['status'] = 'neutral';
        $this->portfolio['xirr'] = 0;

        $this->update($this->portfolio);

        $this->addResponse('Portfolio has no transactions. Nothing to calculate!', 1);

        return false;
    }

    protected function processTransactionsNumbers($data, $viaAddUpdate = false, &$timelineDate = null)
    {
        foreach ($this->portfolio['transactions'] as $transactionId => &$transaction) {
            if ($timelineDate &&
                (\Carbon\Carbon::parse($transaction['date']))->gt(\Carbon\Carbon::parse($timelineDate))
            ) {
                unset($this->portfolio['transactions'][$transactionId]);

                continue;
            }

            if (!isset($transaction['available_amount'])) {
                $transaction['available_amount'] = 0;
            }

            if ($transaction['type'] === 'buy') {
                $this->scheme = $this->schemesPackage->getSchemeFromAmfiCodeOrSchemeId($transaction);

                if ($transaction['status'] === 'open') {
                    $this->investments[$transaction['amfi_code']]['amc_id'] = $this->scheme['amc_id'];
                    $this->investments[$transaction['amfi_code']]['scheme_id'] = $this->scheme['id'];

                    if (!$viaAddUpdate) {
                        $this->transactionsPackage->calculateTransactionUnitsAndValues($transaction, false, $timelineDate, null);
                    }

                    if (isset($this->investments[$transaction['amfi_code']]['units'])) {
                        $this->investments[$transaction['amfi_code']]['units'] += $transaction['units_bought'] - $transaction['units_sold'];
                    } else {
                        $this->investments[$transaction['amfi_code']]['units'] = $transaction['units_bought'] - $transaction['units_sold'];
                    }
                    if (!isset($this->investments[$transaction['amfi_code']]['latest_nav'])) {
                        $this->investments[$transaction['amfi_code']]['latest_nav'] = $this->helper->last($transaction['returns'])['nav'];
                    }
                    if (!isset($this->investments[$transaction['amfi_code']]['latest_nav_date'])) {
                        $this->investments[$transaction['amfi_code']]['latest_nav_date'] = $this->helper->last($transaction['returns'])['date'];
                    }
                    if (!isset($this->investments[$transaction['amfi_code']]['xirrDatesArr'])) {
                        $this->investments[$transaction['amfi_code']]['xirrDatesArr'] = [];
                    }
                    if (!isset($this->investments[$transaction['amfi_code']]['xirrAmountsArr'])) {
                        $this->investments[$transaction['amfi_code']]['xirrAmountsArr'] = [];
                    }

                    if ($transaction['latest_value'] == 0) {
                        $transaction['diff'] = 0;
                        $transaction['xirr'] = 0;
                    } else {
                        if ($transaction['units_sold'] > 0) {
                            $transactionXirrDatesArr = [$this->helper->last($transaction['returns'])['date']];
                            $transactionXirrAmountsArr = [(float) $transaction['latest_value']];

                            $soldAmount = 0;
                            foreach ($transaction['transactions'] as $soldTransaction) {
                                array_push($transactionXirrDatesArr, $soldTransaction['date']);
                                array_push($transactionXirrAmountsArr, $soldTransaction['amount']);
                                $soldAmount = $soldAmount + $soldTransaction['amount'];
                            }

                            array_push($transactionXirrDatesArr, $transaction['date']);
                            array_push($transactionXirrAmountsArr, -$transaction['amount']);

                            if ($transaction['available_amount'] < $soldAmount) {
                                $transaction['available_amount'] = (float) numberFormatPrecision($transaction['amount'] - $soldAmount, 2);

                                if ($transaction['available_amount'] < 0) {
                                    $transaction['available_amount'] = (float) 0;
                                }
                            } else {
                                $transaction['available_amount'] = (float) numberFormatPrecision($transaction['amount'] - $soldAmount, 2);
                            }

                            if (isset($this->investments[$transaction['amfi_code']]['amount'])) {
                                $this->investments[$transaction['amfi_code']]['amount'] += (float) $transaction['available_amount'];
                            } else {
                                $this->investments[$transaction['amfi_code']]['amount'] = (float) $transaction['available_amount'];
                            }

                            $transaction['diff'] = $transaction['latest_value'] - $transaction['available_amount'];
                            array_push($this->investments[$transaction['amfi_code']]['xirrDatesArr'], $transaction['date']);
                            array_push($this->investments[$transaction['amfi_code']]['xirrAmountsArr'], (float) -$transaction['available_amount']);

                            if (isset($this->investments[$transaction['amfi_code']]['sold_amount'])) {
                                $this->investments[$transaction['amfi_code']]['sold_amount'] += (float) $soldAmount;
                            } else {
                                $this->investments[$transaction['amfi_code']]['sold_amount'] = (float) $soldAmount;
                            }
                        } else {
                            $diff = $this->helper->last($transaction['returns'])['total_return'] - $this->helper->first($transaction['returns'])['total_return'];
                            $transaction['diff'] = numberFormatPrecision((float) $diff, 2);

                            $transactionXirrDatesArr = [$this->helper->last($transaction['returns'])['date'], $transaction['date']];
                            $transactionXirrAmountsArr = [(float) $this->helper->last($transaction['returns'])['total_return'], (float) -$transaction['amount']];

                            array_push($this->investments[$transaction['amfi_code']]['xirrDatesArr'], $transaction['date']);
                            array_push($this->investments[$transaction['amfi_code']]['xirrAmountsArr'], (float) -$transaction['amount']);
                            $transaction['available_amount'] = $transaction['amount'];
                            $transaction['units_sold'] = 0;


                            if (isset($this->investments[$transaction['amfi_code']]['amount'])) {
                                $this->investments[$transaction['amfi_code']]['amount'] += (float) $transaction['amount'];
                            } else {
                                $this->investments[$transaction['amfi_code']]['amount'] = (float) $transaction['amount'];
                            }

                            if (isset($this->investments[$transaction['amfi_code']]['sold_amount'])) {
                                $this->investments[$transaction['amfi_code']]['sold_amount'] += 0;
                            } else {
                                $this->investments[$transaction['amfi_code']]['sold_amount'] = 0;
                            }
                        }

                        $transaction['xirr'] =
                            numberFormatPrecision(
                                (float) \PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Variable\NonPeriodic::rate(
                                    array_values($transactionXirrAmountsArr),
                                    array_values($transactionXirrDatesArr)
                                ) * 100, 2
                            );
                    }
                } else if ($transaction['status'] === 'close') {
                    $transaction['latest_value_date'] = $transaction['date_closed'];
                    $transaction['latest_value'] = 0;
                    $transaction['diff'] = 0;
                    $transaction['available_amount'] = 0;
                    $transaction['xirr'] = 0;

                    $soldAmount = 0;
                    foreach ($transaction['transactions'] as $soldTransaction) {
                        $soldAmount = $soldAmount + $soldTransaction['amount'];
                    }

                    if (isset($this->investments[$transaction['amfi_code']]['sold_amount'])) {
                        $this->investments[$transaction['amfi_code']]['sold_amount'] += (float) $soldAmount;
                    } else {
                        $this->investments[$transaction['amfi_code']]['sold_amount'] = (float) $soldAmount;
                    }
                }
            }

            if (!$timelineDate) {
                $this->transactionsPackage->update($transaction);
            }
        }
    }

    protected function processInvestmentNumbers($timelineDate = null)
    {
        if ($timelineDate) {
            foreach ($this->portfolio['investments'] as $investmentAmficode => $portfolioInvestmentArr) {
                if (!isset($this->investments[$investmentAmficode])) {
                    unset($this->portfolio['investments'][$investmentAmficode]);
                }
            }
        }

        if (count($this->investments) > 0) {
            // trace([$this->investments]);
            $categoriesPackage = $this->usepackage(MfCategories::class);

            $this->portfolio['invested_amount'] = 0;
            $this->portfolio['sold_amount'] = 0;
            $this->portfolio['return_amount'] = 0;
            $this->portfolio['total_value'] = 0;
            $this->portfolio['allocation'] = [];
            $this->portfolio['allocation']['by_schemes'] = [];
            $this->portfolio['allocation']['by_categories'] = [];
            $this->portfolio['allocation']['by_subcategories'] = [];

            foreach ($this->investments as $amfiCode => &$investment) {
                // var_dump($this->investments, $investment);
                if (isset($this->portfolio['investments'][$amfiCode])) {
                    $portfolioInvestment = $this->portfolio['investments'][$amfiCode];

                    if (count($investment) == 1 && isset($investment['sold_amount'])) {
                        if ($portfolioInvestment['status'] === 'open') {
                            $portfolioInvestment['status'] = 'close';
                        }

                        $portfolioInvestment['amount'] = 0;
                        $portfolioInvestment['units'] = 0;
                        $portfolioInvestment['latest_value'] = 0;
                        $portfolioInvestment['sold_amount'] = $investment['sold_amount'];
                        $this->portfolio['sold_amount'] += $investment['sold_amount'];

                        $this->investmentsPackage->update($portfolioInvestment);

                        continue;
                    }
                }

                $portfolioInvestment['sold_amount'] = $investment['sold_amount'];
                $this->portfolio['sold_amount'] += $investment['sold_amount'];
                $portfolioInvestment['status'] = 'open';
                $portfolioInvestment['amc_id'] = $investment['amc_id'];
                $portfolioInvestment['scheme_id'] = $investment['scheme_id'];
                $portfolioInvestment['account_id'] = $this->portfolio['account_id'];
                $portfolioInvestment['user_id'] = $this->portfolio['user_id'];
                $portfolioInvestment['portfolio_id'] = $this->portfolio['id'];
                $portfolioInvestment['amfi_code'] = $amfiCode;
                $this->portfolio['invested_amount'] += $investment['amount'];
                $portfolioInvestment['amount'] = numberFormatPrecision($investment['amount'], 2);
                $portfolioInvestment['units'] = $investment['units'];
                $portfolioInvestment['latest_value'] = $this->investments[$amfiCode]['latest_value'] = numberFormatPrecision($investment['latest_nav'] * $investment['units'], 2);
                $this->portfolio['return_amount'] += $portfolioInvestment['latest_value'];
                $portfolioInvestment['latest_value_date'] = $investment['latest_nav_date'];
                $portfolioInvestment['diff'] = numberFormatPrecision($portfolioInvestment['latest_value'] - $portfolioInvestment['amount'], 2);

                array_push($investment['xirrDatesArr'], $investment['latest_nav_date']);
                array_push($investment['xirrAmountsArr'], (float) $portfolioInvestment['latest_value']);

                $portfolioInvestment['xirr'] =
                    numberFormatPrecision(
                        (float) \PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Variable\NonPeriodic::rate(
                            array_values($investment['xirrAmountsArr']),
                            array_values($investment['xirrDatesArr'])
                        ) * 100, 2
                    );

                $this->portfolioXirrDatesArr = array_merge($this->portfolioXirrDatesArr, $investment['xirrDatesArr']);
                $this->portfolioXirrAmountsArr = array_merge($this->portfolioXirrAmountsArr, $investment['xirrAmountsArr']);

                if ($timelineDate) {
                    $this->portfolio['investments'][$amfiCode] =
                        array_replace($this->portfolio['investments'][$amfiCode], $portfolioInvestment);

                    $investment['id'] = $portfolioInvestment['id'];
                } else {
                    if (array_key_exists('id', $portfolioInvestment)) {
                        $this->investmentsPackage->update($portfolioInvestment);
                    } else {
                        $this->investmentsPackage->add($portfolioInvestment);
                    }

                    $investment['id'] = $this->investmentsPackage->packagesData->last['id'];
                }
            }

            //Running loop again to recalculate category percentage. We need to calculate the portfolio total in order to get percentage of categories.
            unset($investment);//Unset as we are using the same var $investment again.
            foreach ($this->investments as $investment) {
                if (!isset($investment['scheme_id'])) {
                    continue;
                }

                $scheme = $this->schemesPackage->getSchemeById($investment['scheme_id']);

                if (!isset($this->portfolio['allocation']['by_schemes'][$scheme['id']])) {
                    $this->portfolio['allocation']['by_schemes'][$scheme['id']] = [];
                }

                $schemeAllocation = &$this->portfolio['allocation']['by_schemes'][$scheme['id']];
                $schemeAllocation['scheme_id'] = $scheme['id'];
                $schemeAllocation['scheme_name'] = $scheme['name'];
                $schemeAllocation['invested_amount'] = $investment['amount'];
                $schemeAllocation['invested_percent'] = round(($investment['amount'] / $this->portfolio['invested_amount']) * 100, 2);
                $schemeAllocation['return_amount'] = $investment['latest_value'];
                $schemeAllocation['return_percent'] = round(($investment['latest_value'] / $this->portfolio['return_amount']) * 100, 2);

                if (!isset($schemeAllocation['investments'])) {
                    $schemeAllocation['investments'] = [];
                }

                array_push($schemeAllocation['investments'], $investment['id']);

                $categoryId = $scheme['category_id'];

                if (isset($scheme['category']['parent_id'])) {
                    $parent = $categoriesPackage->getMfCategoryParent($scheme['category_id']);

                    if ($parent && isset($parent['id'])) {
                        $categoryId = $parent['id'];
                    }
                }

                if (!isset($this->portfolio['allocation']['by_categories'][$categoryId])) {
                    $this->portfolio['allocation']['by_categories'][$categoryId] = [];
                    $this->portfolio['allocation']['by_categories'][$categoryId]['category'] = $parent;
                }

                $parentCategory = &$this->portfolio['allocation']['by_categories'][$categoryId];

                if (isset($parent)) {
                    $this->portfolio['allocation']['by_categories'][$categoryId]['category'] = $parent;

                    if (!isset($this->portfolio['allocation']['by_subcategories'][$scheme['category_id']])) {
                        $this->portfolio['allocation']['by_subcategories'][$scheme['category_id']]['category'] = $scheme['category'];

                        //Referencing for cleaner code
                        $subCategory = &$this->portfolio['allocation']['by_subcategories'][$scheme['category_id']];
                    }

                    if (!isset($subCategory['invested_amount'])) {
                        $subCategory['invested_amount'] = $investment['amount'];
                    } else {
                        $subCategory['invested_amount'] += $investment['amount'];
                    }

                    $subCategory['invested_percent'] =
                        round(($subCategory['invested_amount'] / $this->portfolio['invested_amount']) * 100, 2);

                    if (!isset($subCategory['return_amount'])) {
                        $subCategory['return_amount'] = $investment['latest_value'];
                    } else {
                        $subCategory['return_amount'] =
                            numberFormatPrecision($subCategory['return_amount'] + $investment['latest_value'], 2);
                    }

                    $subCategory['return_percent'] =
                        round(($subCategory['return_amount'] / $this->portfolio['return_amount']) * 100, 2);

                    if (!isset($subCategory['investments'])) {
                        $subCategory['investments'] = [];
                    }

                    array_push($subCategory['investments'], $investment['id']);
                }

                if (!isset($parentCategory['invested_amount'])) {
                    $parentCategory['invested_amount'] = $investment['amount'];
                } else {
                    $parentCategory['invested_amount'] += $investment['amount'];
                }

                $parentCategory['invested_percent'] =
                    round(($parentCategory['invested_amount'] / $this->portfolio['invested_amount']) * 100, 2);

                if (!isset($parentCategory['return_amount'])) {
                    $parentCategory['return_amount'] = $investment['latest_value'];
                } else {
                    $parentCategory['return_amount'] =
                        numberFormatPrecision($parentCategory['return_amount'] + $investment['latest_value'], 2);
                }

                $parentCategory['return_percent'] =
                    round(($parentCategory['return_amount'] / $this->portfolio['return_amount']) * 100, 2);

                if (!isset($parentCategory['investments'])) {
                    $parentCategory['investments'] = [];
                }

                array_push($parentCategory['investments'], $investment['id']);
            }
        }

        $this->portfolio['total_value'] = $this->portfolio['return_amount'] + $this->portfolio['sold_amount'];

        $this->portfolio['profit_loss'] = numberFormatPrecision($this->portfolio['return_amount'] - $this->portfolio['invested_amount'], 2);

        if ($this->portfolio['profit_loss'] > 0) {
            $this->portfolio['status'] = 'positive';
        } else if ($this->portfolio['profit_loss'] < 0) {
            $this->portfolio['status'] = 'negative';
        } else if ($this->portfolio['profit_loss'] == 0) {
            $this->portfolio['status'] = 'neutral';
        }

        if (count($this->portfolioXirrDatesArr) > 0 &&
            count($this->portfolioXirrAmountsArr) > 0 &&
            (count($this->portfolioXirrDatesArr) == count($this->portfolioXirrAmountsArr))
        ) {
            $this->portfolio['xirr'] =
                numberFormatPrecision(
                    (float) \PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Variable\NonPeriodic::rate(
                        array_values($this->portfolioXirrAmountsArr),
                        array_values($this->portfolioXirrDatesArr)
                    ) * 100, 2
                );
        } else {
            $this->portfolio['xirr'] = 0;
        }

        if ($timelineDate) {
            $this->portfolio['timelineDate'] = $timelineDate;
        }
    }
}