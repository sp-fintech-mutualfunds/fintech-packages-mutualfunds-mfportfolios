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

    protected $portfoliotimelinePackage;

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

        $this->portfoliotimelinePackage = $this->usePackage(MfPortfoliostimeline::class);

        $this->transactionsPackage = $this->usepackage(MfTransactions::class);

        $this->investmentsPackage = $this->usepackage(MfInvestments::class);

        $this->schemesPackage = $this->usepackage(MfSchemes::class);

        parent::init();

        return $this;
    }

    public function getPortfolioById(int $id, $getTimeline = false)
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

            if (isset($portfolio['timeline']) && !$getTimeline) {
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
            $portfoliotimeline['portfolio_id'] = $this->packagesData->last['id'];
            $portfoliotimeline['snapshots_ids'] = $this->helper->encode([]);
            $portfoliotimeline['performance_chunks_ids'] = $this->helper->encode([]);
            $portfoliotimeline['mode'] = 'transactions';

            $this->portfoliotimelinePackage->add($portfoliotimeline);

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

        if (isset($data['clone_portfolio_description'])) {
            $mfportfolios['description'] = $data['clone_portfolio_description'];
        } else {
            $mfportfolios['description'] = $mfportfolios['description'] . ' NOTE: This portfolio is a clone of ' . $mfportfolios['name'];
        }

        if (isset($data['clone_portfolio_name'])) {
            if (!checkCtype($data['clone_portfolio_name'], 'alnum', ['_', '-', ':'])) {
                $this->addResponse('Name cannot have special chars other than dash, underscore or colon.', 1);

                return false;
            }

            $mfportfolios['name'] = $data['clone_portfolio_name'];
        } else {
            $mfportfolios['name'] = $mfportfolios['name'] . '_clone_' . str_replace(' ', '_', (\Carbon\Carbon::now(new \DateTimeZone('Asia/Kolkata')))->toDateTimeString());
        }

        unset($mfportfolios['id']);

        if ($this->add($mfportfolios)) {
            $newPortfolioId = $this->packagesData->last['id'];

            if (isset($mfportfolios['timeline']['id'])) {
                unset($mfportfolios['timeline']['id']);
            }

            if (isset($mfportfolios['timeline']['snapshots_ids'])) {
                $portfoliotimeline['snapshots_ids'] = $mfportfolios['timeline']['snapshots_ids'];
            } else {
                $portfoliotimeline['snapshots_ids'] = $this->helper->encode([]);
            }

            if (isset($mfportfolios['timeline']['performance_chunks_ids'])) {
                $portfoliotimeline['performance_chunks_ids'] = $mfportfolios['timeline']['performance_chunks_ids'];
            } else {
                $portfoliotimeline['performance_chunks_ids'] = $this->helper->encode([]);
            }

            $portfoliotimeline['mode'] = $mfportfolios['timeline']['mode'];

            $portfoliotimeline['portfolio_id'] = $newPortfolioId;

            $this->portfoliotimelinePackage->add($portfoliotimeline);

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

            if (!isset($data['strategy_id'])) {
                $this->recalculatePortfolio(['portfolio_id' => $newPortfolioId]);
            }

            $this->addResponse('Portfolio Added');

            return $newPortfolioId;
        } else {
            $this->addResponse('Error Adding Portfolio', 1);
        }
    }

    public function removePortfolio($data)
    {
        $mfportfolios = $this->getPortfolioById($data['id'], true);

        if ($mfportfolios) {
            //Remove Timeline
            if (isset($mfportfolios['timeline']['id'])) {
                if (isset($mfportfolios['timeline']['snapshots_ids']) &&
                    count($mfportfolios['timeline']['snapshots_ids']) > 0
                ) {
                    $this->portfoliotimelinePackage->switchModel($this->portfoliotimelinePackage->snapshotsModel);

                    foreach ($mfportfolios['timeline']['snapshots_ids'] as $snapshotId) {
                        $this->portfoliotimelinePackage->remove($snapshotId);
                    }
                }

                if (isset($mfportfolios['timeline']['performance_chunks_ids']) &&
                    count($mfportfolios['timeline']['performance_chunks_ids']) > 0
                ) {
                    $this->portfoliotimelinePackage->switchModel($this->portfoliotimelinePackage->performanceChunksModel);

                    foreach ($mfportfolios['timeline']['performance_chunks_ids'] as $performanceChunkId) {
                        $this->portfoliotimelinePackage->remove($performanceChunkId);
                    }
                }

                $this->portfoliotimelinePackage->switchModel();

                $this->portfoliotimelinePackage->remove($mfportfolios['timeline']['id']);

                //Remove Timeline Opcache entry
                // if ($this->opCache->checkCache($mfportfolios['timeline']['id'], 'mfportfoliostimeline')) {
                //     $this->opCache->removeCache($mfportfolios['timeline']['id'], 'mfportfoliostimeline');
                // }
            }
            //Remove Transactions
            if (isset($mfportfolios['transactions']) && count($mfportfolios['transactions']) > 0) {
                foreach ($mfportfolios['transactions'] as $transaction) {
                    $this->transactionsPackage->remove($transaction['id']);
                }
            }
            //Remove Transactions
            if (isset($mfportfolios['investments']) && count($mfportfolios['investments']) > 0) {
                foreach ($mfportfolios['investments'] as $investment) {
                    $this->investmentsPackage->remove($investment['id']);
                }
            }

            if ($this->remove($mfportfolios['id'])) {
                $this->addResponse('Success');

                return;
            }
        }

        $this->addResponse('Error', 1);
    }

    public function recalculatePortfolio($data, $force = false, $timeline = null)
    {
        if (!$this->portfolio || $force) {
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
            $this->portfolio['transactions'] = msort(array: $this->portfolio['transactions'], key: 'date', preserveKey: true);

            $this->processTransactionsNumbers($force, $timeline);

            $this->processInvestmentNumbers($timeline);
            // trace([$this->portfolio]);
            if ($timeline) {
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

        if ($this->portfolio['investments'] && count($this->portfolio['investments']) > 0) {
            foreach ($this->portfolio['investments'] as $investment) {
                $this->investmentsPackage->remove($investment['id']);
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

    protected function processTransactionsNumbers($force = false, &$timeline = null)
    {
        foreach ($this->portfolio['transactions'] as $transactionId => &$transaction) {
            if ($timeline &&
                $timeline->timelineDateBeingProcessed &&
                (\Carbon\Carbon::parse($transaction['date']))->gt(\Carbon\Carbon::parse($timeline->timelineDateBeingProcessed))
            ) {
                unset($this->portfolio['transactions'][$transactionId]);

                continue;
            }

            if (!isset($transaction['available_amount'])) {
                $transaction['available_amount'] = 0;
            }

            if ($transaction['type'] === 'buy') {
                if (!isset($this->investments[$transaction['amfi_code']])) {
                    $this->investments[$transaction['amfi_code']] = [];
                    $this->investments[$transaction['amfi_code']]['start_date'] = $transaction['date'];
                }

                if (!isset($this->investments[$transaction['amfi_code']]['total_investment'])) {
                    $this->investments[$transaction['amfi_code']]['total_investment'] = 0;

                }
                $this->investments[$transaction['amfi_code']]['total_investment'] =
                    $this->investments[$transaction['amfi_code']]['total_investment'] + $transaction['amount'];

                if (\Carbon\Carbon::parse($this->portfolio['start_date'])->gt(\Carbon\Carbon::parse($transaction['date']))) {
                    $this->portfolio['start_date'] = $transaction['date'];
                }

                $this->scheme = $this->schemesPackage->getSchemeFromAmfiCodeOrSchemeId($transaction);

                if ($transaction['status'] === 'close' &&
                    \Carbon\Carbon::parse($timeline->timelineDateBeingProcessed)->lte(\Carbon\Carbon::parse($transaction['date_closed']))
                ) {
                    $transaction['status'] = 'open';
                }

                if ($transaction['status'] === 'open') {
                    $this->investments[$transaction['amfi_code']]['amc_id'] = $this->scheme['amc_id'];
                    $this->investments[$transaction['amfi_code']]['scheme_id'] = $this->scheme['id'];

                    if (!$force) {
                        $this->transactionsPackage->calculateTransactionUnitsAndValues($transaction, false, $timeline, null);
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
                        $transactionXirrDatesArr = [];
                        $transactionXirrAmountsArr = [];

                        if (isset($transaction['transactions']) && count($transaction['transactions']) > 0) {
                            array_push($transactionXirrDatesArr, $transaction['date']);
                            array_push($transactionXirrAmountsArr, -$transaction['amount']);

                            $soldAmount = 0;
                            $soldUnits = 0;
                            foreach ($transaction['transactions'] as $soldTransaction) {
                                if ($timeline &&
                                    $timeline->timelineDateBeingProcessed &&
                                    (\Carbon\Carbon::parse($soldTransaction['date']))->gt(\Carbon\Carbon::parse($timeline->timelineDateBeingProcessed))
                                ) {
                                    continue;
                                }

                                array_push($transactionXirrDatesArr, $soldTransaction['date']);
                                array_push($transactionXirrAmountsArr, $soldTransaction['amount']);

                                $soldAmount = numberFormatPrecision($soldAmount + $soldTransaction['amount'], 2);
                                $soldUnits = $soldUnits + $soldTransaction['units'];
                            }

                            array_push($transactionXirrDatesArr, $this->helper->last($transaction['returns'])['date']);
                            array_push($transactionXirrAmountsArr, (float) $transaction['latest_value']);

                            if ($transaction['available_amount'] < $soldAmount) {
                                $transaction['available_amount'] = (float) numberFormatPrecision($transaction['amount'] - $soldAmount, 2);

                                // if ($transaction['available_amount'] < 0) {
                                //     $transaction['available_amount'] = abs($transaction['available_amount']);
                                // }
                            } else {
                                $transaction['available_amount'] = (float) numberFormatPrecision($transaction['amount'] - $soldAmount, 2);
                            }

                            if (isset($this->investments[$transaction['amfi_code']]['amount'])) {
                                $this->investments[$transaction['amfi_code']]['amount'] += (float) $transaction['available_amount'];
                            } else {
                                $this->investments[$transaction['amfi_code']]['amount'] = (float) $transaction['available_amount'];
                            }

                            $transaction['diff'] = $transaction['latest_value'] - $transaction['available_amount'];
                            $this->investments[$transaction['amfi_code']]['xirrDatesArr'] =
                                array_merge($this->investments[$transaction['amfi_code']]['xirrDatesArr'], $transactionXirrDatesArr);
                            $this->investments[$transaction['amfi_code']]['xirrAmountsArr'] =
                                array_merge($this->investments[$transaction['amfi_code']]['xirrAmountsArr'], $transactionXirrAmountsArr);
                            // array_push($this->investments[$transaction['amfi_code']]['xirrDatesArr'], $transaction['date']);
                            // if ($transaction['available_amount'] < 0) {
                            //     array_push($this->investments[$transaction['amfi_code']]['xirrAmountsArr'], (float) -($transaction['amount'] + abs($transaction['available_amount'])));
                            // } else {
                            //     array_push($this->investments[$transaction['amfi_code']]['xirrAmountsArr'], (float) -$transaction['available_amount']);
                            // }

                            if (isset($this->investments[$transaction['amfi_code']]['sold_amount'])) {
                                $this->investments[$transaction['amfi_code']]['sold_amount'] += (float) $soldAmount;
                            } else {
                                $this->investments[$transaction['amfi_code']]['sold_amount'] = (float) $soldAmount;
                            }

                            if (isset($this->investments[$transaction['amfi_code']]['units'])) {
                                $this->investments[$transaction['amfi_code']]['units'] += (float) $transaction['units_bought'] - $soldUnits;
                            } else {
                                $this->investments[$transaction['amfi_code']]['units'] = (float) $transaction['units_bought'] - $soldUnits;
                            }
                        } else {
                            $diff = $this->helper->last($transaction['returns'])['total_return'] - $this->helper->first($transaction['returns'])['total_return'];
                            $transaction['diff'] = numberFormatPrecision((float) $diff, 2);

                            $this->investments[$transaction['amfi_code']]['xirrDatesArr'] =
                                $transactionXirrDatesArr = [$this->helper->last($transaction['returns'])['date'], $transaction['date']];
                            $this->investments[$transaction['amfi_code']]['xirrAmountsArr'] =
                                $transactionXirrAmountsArr = [(float) $this->helper->last($transaction['returns'])['total_return'], (float) -$transaction['amount']];

                            // array_push($this->investments[$transaction['amfi_code']]['xirrDatesArr'], $transaction['date']);
                            // array_push($this->investments[$transaction['amfi_code']]['xirrAmountsArr'], (float) -$transaction['amount']);
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

                            if (isset($this->investments[$transaction['amfi_code']]['units'])) {
                                $this->investments[$transaction['amfi_code']]['units'] += (float) $transaction['units_bought'] - $transaction['units_sold'];
                            } else {
                                $this->investments[$transaction['amfi_code']]['units'] = (float) $transaction['units_bought'] - $transaction['units_sold'];
                            }
                        }
                        // trace([$transactionXirrAmountsArr, $transactionXirrDatesArr]);
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
            // trace([$this->investments]);
            if (!$timeline) {
                $this->transactionsPackage->update($transaction);
            }
        }
    }

    protected function processInvestmentNumbers($timeline = null)
    {
        if ($this->portfolio['investments']) {
            foreach ($this->portfolio['investments'] as $investmentAmficode => $portfolioInvestmentArr) {
                if (!isset($this->investments[$investmentAmficode])) {
                    if ($timeline) {
                        unset($this->portfolio['investments'][$investmentAmficode]);
                    } else {
                        $this->investmentsPackage->remove($this->portfolio['investments'][$investmentAmficode]['id']);
                    }
                }
            }
        }

        if (count($this->investments) > 0) {
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

                $portfolioInvestment['start_date'] = $investment['start_date'];
                $portfolioInvestment['sold_amount'] = $investment['sold_amount'];
                $this->portfolio['sold_amount'] += $investment['sold_amount'];
                $portfolioInvestment['status'] = 'open';
                $portfolioInvestment['amc_id'] = $investment['amc_id'];
                $portfolioInvestment['scheme_id'] = $investment['scheme_id'];
                $portfolioInvestment['account_id'] = $this->portfolio['account_id'];
                $portfolioInvestment['user_id'] = $this->portfolio['user_id'];
                $portfolioInvestment['portfolio_id'] = $this->portfolio['id'];
                $portfolioInvestment['amfi_code'] = $amfiCode;
                // $this->portfolio['invested_amount'] += $investment['amount'];
                $this->portfolio['invested_amount'] += $investment['total_investment'];
                $portfolioInvestment['amount'] = numberFormatPrecision($investment['total_investment'], 2);
                $portfolioInvestment['units'] = numberFormatPrecision($investment['units'], 3);
                $portfolioInvestment['latest_nav'] = numberFormatPrecision($investment['latest_nav'], 2);
                $portfolioInvestment['latest_value'] =
                    $this->investments[$amfiCode]['latest_value'] =
                        numberFormatPrecision($portfolioInvestment['latest_nav'] * $portfolioInvestment['units'], 2);
                $this->portfolio['return_amount'] += $portfolioInvestment['latest_value'];
                $portfolioInvestment['latest_value_date'] = $investment['latest_nav_date'];
                $portfolioInvestment['diff'] = numberFormatPrecision($portfolioInvestment['latest_value'] - $portfolioInvestment['amount'], 2);

                // array_push($investment['xirrDatesArr'], $investment['latest_nav_date']);
                // array_push($investment['xirrAmountsArr'], (float) $portfolioInvestment['latest_value']);

                // trace([$investment['xirrAmountsArr'], $investment['xirrDatesArr']]);
                $portfolioInvestment['xirr'] =
                    numberFormatPrecision(
                        (float) \PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Variable\NonPeriodic::rate(
                            array_values($investment['xirrAmountsArr']),
                            array_values($investment['xirrDatesArr'])
                        ) * 100, 2
                    );

                $this->portfolioXirrDatesArr = array_merge($this->portfolioXirrDatesArr, $investment['xirrDatesArr']);
                $this->portfolioXirrAmountsArr = array_merge($this->portfolioXirrAmountsArr, $investment['xirrAmountsArr']);

                if ($timeline) {
                    // trace([$timeline->timelineDateBeingProcessed, $this->portfolio['transactions'], $this->investments, $portfolioInvestment]);
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
            // trace([$this->portfolio]);
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
                $schemeAllocation['invested_amount'] = $investment['total_investment'];
                $schemeAllocation['invested_percent'] = round(($investment['total_investment'] / $this->portfolio['invested_amount']) * 100, 2);
                $schemeAllocation['return_amount'] = $investment['latest_value'];
                if ($investment['latest_value'] == 0) {
                    $schemeAllocation['return_percent'] = 0;
                } else {
                    $schemeAllocation['return_percent'] = round(($investment['latest_value'] / $this->portfolio['return_amount']) * 100, 2);
                }

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
                        $subCategory['invested_amount'] = $investment['total_investment'];
                    } else {
                        $subCategory['invested_amount'] += $investment['total_investment'];
                    }

                    $subCategory['invested_percent'] =
                        round(($subCategory['invested_amount'] / $this->portfolio['invested_amount']) * 100, 2);

                    if (!isset($subCategory['return_amount'])) {
                        $subCategory['return_amount'] = $investment['latest_value'];
                    } else {
                        $subCategory['return_amount'] =
                            numberFormatPrecision($subCategory['return_amount'] + $investment['latest_value'], 2);
                    }

                    if ($subCategory['return_amount'] == 0) {
                        $subCategory['return_percent'] = 0;
                    } else {
                        $subCategory['return_percent'] =
                            round(($subCategory['return_amount'] / $this->portfolio['return_amount']) * 100, 2);
                    }

                    if (!isset($subCategory['investments'])) {
                        $subCategory['investments'] = [];
                    }

                    array_push($subCategory['investments'], $investment['id']);
                }

                if (!isset($parentCategory['invested_amount'])) {
                    $parentCategory['invested_amount'] = $investment['total_investment'];
                } else {
                    $parentCategory['invested_amount'] += $investment['total_investment'];
                }

                $parentCategory['invested_percent'] =
                    round(($parentCategory['invested_amount'] / $this->portfolio['invested_amount']) * 100, 2);

                if (!isset($parentCategory['return_amount'])) {
                    $parentCategory['return_amount'] = $investment['latest_value'];
                } else {
                    $parentCategory['return_amount'] =
                        numberFormatPrecision($parentCategory['return_amount'] + $investment['latest_value'], 2);
                }

                if ($parentCategory['return_amount'] == 0) {
                    $parentCategory['return_percent'] = 0;
                } else {
                    $parentCategory['return_percent'] =
                        round(($parentCategory['return_amount'] / $this->portfolio['return_amount']) * 100, 2);
                }

                if (!isset($parentCategory['investments'])) {
                    $parentCategory['investments'] = [];
                }

                array_push($parentCategory['investments'], $investment['id']);
            }
        }

        $this->portfolio['total_value'] = $this->portfolio['return_amount'] + $this->portfolio['sold_amount'];

        if ($this->portfolio['sold_amount'] > 0) {
            $investedAmount = $this->portfolio['invested_amount'] + abs($this->portfolio['invested_amount'] - $this->portfolio['sold_amount']);
        } else {
            $investedAmount = $this->portfolio['invested_amount'];
        }

        $this->portfolio['profit_loss'] = numberFormatPrecision($this->portfolio['return_amount'] - $investedAmount, 2);

        if ($this->portfolio['profit_loss'] > 0) {
            $this->portfolio['status'] = 'positive';
        } else if ($this->portfolio['profit_loss'] < 0) {
            $this->portfolio['status'] = 'negative';
        } else if ($this->portfolio['profit_loss'] == 0) {
            $this->portfolio['status'] = 'neutral';
        }

        // trace([$this->portfolioXirrAmountsArr, $this->portfolioXirrDatesArr]);
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

        if ($timeline && $timeline->timelineDateBeingProcessed) {
            $this->portfolio['timelineDate'] = $timeline->timelineDateBeingProcessed;
        }
    }
}