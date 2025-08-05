<?php

namespace Apps\Fintech\Packages\Mf\Portfolios;

use Apps\Fintech\Packages\Mf\Categories\MfCategories;
use Apps\Fintech\Packages\Mf\Investments\MfInvestments;
use Apps\Fintech\Packages\Mf\Portfolios\Model\AppsFintechMfPortfolios;
use Apps\Fintech\Packages\Mf\Portfolios\Model\AppsFintechMfPortfoliosPerformancesChunks;
use Apps\Fintech\Packages\Mf\Portfolios\NonPeriodic;
use Apps\Fintech\Packages\Mf\Portfoliostimeline\MfPortfoliostimeline;
use Apps\Fintech\Packages\Mf\Schemes\MfSchemes;
use Apps\Fintech\Packages\Mf\Transactions\MfTransactions;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
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

    public $schemes = [];

    public $parsedCarbon = [];

    protected $investments = [];

    protected $portfolioXirrDatesArr = [];

    protected $portfolioXirrAmountsArr = [];

    public function init()
    {
        $this->today = (\Carbon\Carbon::now(new \DateTimeZone('Asia/Kolkata')));

        $this->parsedCarbon[$this->today->toDateString()] = $this->today;

        $this->today = $this->today->toDateString();

        $this->portfoliotimelinePackage = $this->usePackage(MfPortfoliostimeline::class);

        $this->transactionsPackage = $this->usepackage(MfTransactions::class);

        $this->investmentsPackage = $this->usepackage(MfInvestments::class);

        $this->schemesPackage = $this->usepackage(MfSchemes::class);

        parent::init();

        return $this;
    }

    public function getPortfolioById(int $id, $getTimeline = false, $getTransactions = true, $getInvestments = true, $getAllocation = true, $getPerformancesChunks = true)
    {
        $this->ffStore = $this->ff->store($this->ffStoreToUse);

        $this->setFFRelations(true);

        $this->getFirst('id', $id);

        if ($this->model) {
            $portfolio = $this->model->toArray();

            $portfolio['transactions'] = [];
            if ($this->model->gettransactions()) {
                $portfolio['transactions'] = $this->model->gettransactions()->toArray();
            }

            $portfolio['performances_chunks'] = [];
            if ($this->model->getperformances_chunks()) {
                $portfolio['performances_chunks'] = $this->model->getperformances_chunks()->toArray();
            }
        } else {
            if ($this->ffData) {
                $portfolio = $this->jsonData($this->ffData, true);
            }
        }

        if ($portfolio) {
            if ($portfolio['allocation'] && count($portfolio['allocation']) > 0) {
                if (!$getAllocation) {
                    unset($portfolio['allocation']);
                }
            }

            if ($portfolio['transactions'] && count($portfolio['transactions']) > 0) {
                if (!$getTransactions) {
                    unset($portfolio['transactions']);
                } else {
                    $transactions = $portfolio['transactions'];

                    $portfolio['transactions'] = [];

                    foreach ($transactions as $transaction) {
                        $portfolio['transactions'][$transaction['id']] = $transaction;
                    }
                }
            }

            if ($portfolio['investments'] && count($portfolio['investments']) > 0) {
                if (!$getInvestments) {
                    unset($portfolio['investments']);
                } else {
                    $investments = $portfolio['investments'];

                    $portfolio['investments'] = [];

                    foreach ($investments as $investment) {
                        $portfolio['investments'][$investment['scheme_id']] = $investment;
                    }
                }
            }

            if (isset($portfolio['timeline']) && !$getTimeline) {
                unset($portfolio['timeline']);
            }

            if (isset($portfolio['performances_chunks']) && !$getPerformancesChunks) {
                unset($portfolio['performances_chunks']);
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
        $data['is_clone'] = false;

        if ($this->add($data)) {
            $portfoliotimeline['portfolio_id'] = $this->packagesData->last['id'];
            $portfoliotimeline['snapshots_ids'] = $this->helper->encode([]);

            $this->portfoliotimelinePackage->add($portfoliotimeline);

            $portfolioperformances['portfolio_id'] = $this->packagesData->last['id'];
            $portfolioperformances['performances_chunks'] = $this->helper->encode([]);
            $this->switchModel(AppsFintechMfPortfoliosPerformancesChunks::class);
            $this->add($portfolioperformances);
            $this->switchModel();

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

        if (!$mfportfolios) {
            $this->addResponse('Portfolio with ID : ' . $data['id'] . ' not found', 1);

            return false;
        }

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
        $investments = $mfportfolios['investments'];
        unset($mfportfolios['investments']);

        $transactions = $mfportfolios['transactions'];
        unset($mfportfolios['transactions']);

        $mfportfolios['is_clone'] = true;
        $mfportfolios['investment_source'] = 'virtual';
        $mfportfolios['book_id'] = null;
        $mfportfolios['withdraw_bankaccount_id'] = null;
        $mfportfolios['deposit_bankaccount_id'] = null;

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

            $portfoliotimeline['portfolio_id'] = $newPortfolioId;

            $this->portfoliotimelinePackage->add($portfoliotimeline);

            $portfolioperformances['portfolio_id'] = $this->packagesData->last['id'];
            $portfolioperformances['performances_chunks'] = $this->helper->encode([]);
            $this->switchModel(AppsFintechMfPortfoliosPerformancesChunks::class);
            $this->add($portfolioperformances);
            $this->switchModel();

            if ($transactions && count($transactions) > 0) {
                $transactionsMapping = [];
                $newTransactions = [];

                foreach ($transactions as $transaction) {
                    $transaction['portfolio_id'] = $newPortfolioId;

                    $oldTransactionId = $transaction['id'];

                    unset($transaction['id']);

                    $this->transactionsPackage->add($transaction);

                    $newTransaction = $this->transactionsPackage->packagesData->last;
                    $transactionsMapping[$oldTransactionId] = $newTransaction['id'];
                    $newTransactions[$newTransaction['id']] = $newTransaction;
                }

                if (count($newTransactions) > 0) {
                    $newTransaction = null;

                    foreach ($newTransactions as $newTransaction) {
                        if ($newTransaction['transactions'] && count($newTransaction['transactions']) > 0) {
                            foreach ($newTransaction['transactions'] as $oldId => $newTransactionTransactions) {
                                if (isset($transactionsMapping[$oldId])) {
                                    $newTransaction['transactions'][$transactionsMapping[$oldId]] = $newTransactionTransactions;
                                    $newTransaction['transactions'][$transactionsMapping[$oldId]]['id'] = $transactionsMapping[$oldId];

                                    unset($newTransaction['transactions'][$oldId]);
                                }
                            }

                            $this->transactionsPackage->update($newTransaction);
                        }
                    }
                }
            }

            if ($investments && count($investments) > 0) {
                foreach ($investments as $investment) {
                    $investment['portfolio_id'] = $newPortfolioId;
                    unset($investment['id']);

                    $this->investmentsPackage->add($investment);
                }
            }

            if (!isset($data['strategy_id'])) {
                $this->recalculatePortfolio(['portfolio_id' => $newPortfolioId]);
            }

            $this->addResponse('Portfolio ' . $mfportfolios['name'] . ' cloned successfully.');

            return $newPortfolioId;
        } else {
            $this->addResponse('Error cloning Portfolio', 1);

            return false;
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

                // if (isset($mfportfolios['timeline']['performance_chunks_id'])) {
                //     $this->portfoliotimelinePackage->switchModel($this->portfoliotimelinePackage->performanceChunksModel);

                //     $this->portfoliotimelinePackage->remove($mfportfolios['timeline']['performance_chunks_id']);
                // }

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
                if (isset($mfportfolios['performances']['id'])) {
                    $this->switchModel(AppsFintechMfPortfoliosPerformancesChunks::class);
                    $this->remove($mfportfolios['performances']['id']);
                    $this->switchModel();
                }

                $this->addResponse('Success');

                return;
            }
        }

        $this->addResponse('Error', 1);
    }

    public function recalculatePortfolio($data, $force = false, $timeline = null)
    {
        if ((!$this->portfolio || $force) && !$timeline) {
            $this->portfolio = $this->getPortfolioById((int) $data['portfolio_id']);
        }

        if ($timeline) {
            if ($timeline->portfolio) {
                $this->portfolio = $timeline->portfolio;
            }

            if (count($timeline->portfolioSchemes) > 0) {
                $this->schemes = &$timeline->portfolioSchemes;
            }

            $this->parsedCarbon = &$timeline->parsedCarbon;
        }

        if (!$this->portfolio) {
            $this->addResponse('Portfolio not found', 1);

            return false;
        }

        // if (!isset($this->portfolio['performances'])) {
        //     $portfolioperformances['portfolio_id'] = $this->portfolio['id'];
        //     $portfolioperformances['performances'] = $this->helper->encode([]);
        //     $this->setModelToUse(AppsFintechMfPortfoliosPerformancesChunks::class);
        //     $this->ffStore = null;
        //     $this->add($portfolioperformances);
        //     $this->portfolio['performances'] = $this->packagesData->last;
        // }

        //Increase memory_limit to 1G as the process takes a bit of memory to process the scheme's navs array.
        if ((int) ini_get('memory_limit') < 1024) {
            ini_set('memory_limit', '1024M');
        }

        //Increase Exectimeout to 10 mins as this process takes time to extract and merge data.
        if ((int) ini_get('max_execution_time') < 600) {
            set_time_limit(600);
        }

        if ($this->portfolio['transactions'] && count($this->portfolio['transactions']) > 0) {
            $this->portfolio['transactions'] = msort(array: $this->portfolio['transactions'], key: 'date', preserveKey: true);
            // $this->basepackages->utils->setMicroTimer('transaction start - ' . $timeline->timelineDateBeingProcessed, true);
            // $this->basepackages->utils->setMicroTimer('transaction start', true);
            if (!$this->processTransactionsNumbers($force, $timeline)) {
                return false;
            }
            // $this->basepackages->utils->setMicroTimer('transaction end - ' . $timeline->timelineDateBeingProcessed, true);
            // $this->basepackages->utils->setMicroTimer('transaction end', true);
            // var_Dump($this->basepackages->utils->getMicroTimer());
            // $this->basepackages->utils->resetMicroTimer();
            // $this->processPortfolioPerformances();

            // $this->basepackages->utils->setMicroTimer('investments start - ' . $timeline->timelineDateBeingProcessed, true);
            // $this->basepackages->utils->setMicroTimer('investments start', true);
            if (!$this->processInvestmentNumbers($timeline)) {
                return false;
            }
            // $this->basepackages->utils->setMicroTimer('investments end - ' . $timeline->timelineDateBeingProcessed, true);
            // $this->basepackages->utils->setMicroTimer('investments end', true);
            // var_Dump($this->basepackages->utils->getMicroTimer());
            // $this->basepackages->utils->resetMicroTimer();
            // trace([$this->portfolio]);
            // trace(['me']);
            if ($timeline) {
                return $this->portfolio;
            } else {
                $this->setFFValidation(false);
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
        // $performanceCounter = 0;
        // trace([$this->portfolio['transactions']]);
        foreach ($this->portfolio['transactions'] as $transactionId => &$transaction) {
            // $this->portfolio['performances']['performances'][$performanceCounter] = [];
            // $this->portfolio['performances']['performances'][$performanceCounter]['date'] = $transaction['date'];

            if ($timeline && $timeline->timelineDateBeingProcessed) {
                if (!isset($this->parsedCarbon[$transaction['date']])) {
                    $this->parsedCarbon[$transaction['date']] = \Carbon\Carbon::parse($transaction['date']);
                }

                if (!isset($this->parsedCarbon[$timeline->timelineDateBeingProcessed])) {
                    $this->parsedCarbon[$timeline->timelineDateBeingProcessed] = \Carbon\Carbon::parse($timeline->timelineDateBeingProcessed);
                }

                if (($this->parsedCarbon[$transaction['date']])->gt($this->parsedCarbon[$timeline->timelineDateBeingProcessed])) {
                    unset($this->portfolio['transactions'][$transactionId]);

                    continue;
                }
            }

            if (!isset($transaction['available_amount'])) {
                $transaction['available_amount'] = 0;
            }

            if ($transaction['type'] === 'buy') {
                if (!isset($this->investments[$transaction['scheme_id']])) {
                    $this->investments[$transaction['scheme_id']] = [];
                    $this->investments[$transaction['scheme_id']]['start_date'] = $transaction['date'];
                }

                if (!isset($this->investments[$transaction['scheme_id']]['total_investment'])) {
                    $this->investments[$transaction['scheme_id']]['total_investment'] = 0;
                }

                $this->investments[$transaction['scheme_id']]['total_investment'] =
                    $this->investments[$transaction['scheme_id']]['total_investment'] + $transaction['amount'];

                // // if ($performanceCounter === 0) {
                // //     $this->portfolio['performances']['performances'][$performanceCounter]['total_investment'] = (float) $transaction['amount'];
                // // } else {
                //     $this->portfolio['performances']['performances'][$performanceCounter]['total_investment'] =
                //         (float) $this->portfolio['performances']['performances'][$performanceCounter - 1]['total_investment'] + $transaction['amount'];
                // }

            // $this->basepackages->utils->setMicroTimer('scheme start - ' . $timeline->timelineDateBeingProcessed, true);
                if (!isset($this->parsedCarbon[$this->portfolio['start_date']])) {
                    $this->parsedCarbon[$this->portfolio['start_date']] = \Carbon\Carbon::parse($this->portfolio['start_date']);
                }
                if (!isset($this->parsedCarbon[$transaction['date']])) {
                    $this->parsedCarbon[$transaction['date']] = \Carbon\Carbon::parse($transaction['date']);
                }

                if (($this->parsedCarbon[$this->portfolio['start_date']])->gt($this->parsedCarbon[$transaction['date']])) {
                    $this->portfolio['start_date'] = $transaction['date'];
                }

                // trace([$this->schemes]);
                if (!isset($this->schemes[$transaction['scheme_id']])) {
                    $this->schemes[$transaction['scheme_id']] = $this->schemesPackage->getSchemeFromAmfiCodeOrSchemeId($transaction, true);
                }
                // trace([$this->schemes[$transaction['scheme_id']]]);
                $this->scheme = &$this->schemes[$transaction['scheme_id']];
            // $this->basepackages->utils->setMicroTimer('scheme end - ' . $timeline->timelineDateBeingProcessed, true);
            // var_Dump($this->basepackages->utils->getMicroTimer());
            // $this->basepackages->utils->resetMicroTimer();
            // $this->basepackages->utils->setMicroTimer('transaction start - ' . $timeline->timelineDateBeingProcessed, true);
                if ($transaction['status'] === 'close' && $timeline) {
                    if (!isset($this->parsedCarbon[$timeline->timelineDateBeingProcessed])) {
                        $this->parsedCarbon[$timeline->timelineDateBeingProcessed] = \Carbon\Carbon::parse($timeline->timelineDateBeingProcessed);
                    }

                    if (!isset($this->parsedCarbon[$transaction['date_closed']])) {
                        $this->parsedCarbon[$transaction['date_closed']] = \Carbon\Carbon::parse($transaction['date_closed']);
                    }

                    if (($this->parsedCarbon[$timeline->timelineDateBeingProcessed])->lte($this->parsedCarbon[$transaction['date_closed']])) {
                        $transaction['status'] = 'open';
                    }
                }

                $this->investments[$transaction['scheme_id']]['amc_id'] = $this->scheme['amc_id'];
                $this->investments[$transaction['scheme_id']]['scheme_id'] = $this->scheme['id'];

                $this->investments[$transaction['scheme_id']]['open_transactions'] = false;

                if ($transaction['status'] === 'open') {
                    $this->investments[$transaction['scheme_id']]['open_transactions'] = true;

                    if (!$force) {
                        if (!$this->transactionsPackage->calculateTransactionUnitsAndValues($transaction, false, $timeline, null, $this->schemes)) {
                            $this->addResponse(
                                $this->transactionsPackage->packagesData->responseMessage,
                                $this->transactionsPackage->packagesData->responseCode,
                                $this->transactionsPackage->packagesData->responseData ?? []
                            );
                        }
                    }

                    if (!isset($this->investments[$transaction['scheme_id']]['latest_nav'])) {
                        $this->investments[$transaction['scheme_id']]['latest_nav'] = $this->helper->last($transaction['returns'])['nav'];
                    }
                    if (!isset($this->investments[$transaction['scheme_id']]['latest_nav_date'])) {
                        $this->investments[$transaction['scheme_id']]['latest_nav_date'] = $this->helper->last($transaction['returns'])['date'];
                    }
                    if (!isset($this->investments[$transaction['scheme_id']]['xirrDatesArr'])) {
                        $this->investments[$transaction['scheme_id']]['xirrDatesArr'] = [];
                    }
                    if (!isset($this->investments[$transaction['scheme_id']]['xirrAmountsArr'])) {
                        $this->investments[$transaction['scheme_id']]['xirrAmountsArr'] = [];
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
                                if ($timeline && $timeline->timelineDateBeingProcessed) {
                                    if (!isset($this->parsedCarbon[$soldTransaction['date']])) {
                                        $this->parsedCarbon[$soldTransaction['date']] = \Carbon\Carbon::parse($soldTransaction['date']);
                                    }
                                    if (!isset($this->parsedCarbon[$timeline->timelineDateBeingProcessed])) {
                                        $this->parsedCarbon[$timeline->timelineDateBeingProcessed] = \Carbon\Carbon::parse($timeline->timelineDateBeingProcessed);
                                    }

                                    if (($this->parsedCarbon[$soldTransaction['date']])->gt($this->parsedCarbon[$timeline->timelineDateBeingProcessed])) {
                                        continue;
                                    }
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

                            if (isset($this->investments[$transaction['scheme_id']]['amount'])) {
                                $this->investments[$transaction['scheme_id']]['amount'] += (float) $transaction['available_amount'];
                            } else {
                                $this->investments[$transaction['scheme_id']]['amount'] = (float) $transaction['available_amount'];
                            }

                            $transaction['diff'] = $transaction['latest_value'] - $transaction['available_amount'];
                            $this->investments[$transaction['scheme_id']]['xirrDatesArr'] =
                                array_merge($this->investments[$transaction['scheme_id']]['xirrDatesArr'], $transactionXirrDatesArr);
                            $this->investments[$transaction['scheme_id']]['xirrAmountsArr'] =
                                array_merge($this->investments[$transaction['scheme_id']]['xirrAmountsArr'], $transactionXirrAmountsArr);
                            // array_push($this->investments[$transaction['scheme_id']]['xirrDatesArr'], $transaction['date']);
                            // if ($transaction['available_amount'] < 0) {
                            //     array_push($this->investments[$transaction['scheme_id']]['xirrAmountsArr'], (float) -($transaction['amount'] + abs($transaction['available_amount'])));
                            // } else {
                            //     array_push($this->investments[$transaction['scheme_id']]['xirrAmountsArr'], (float) -$transaction['available_amount']);
                            // }

                            if (isset($this->investments[$transaction['scheme_id']]['sold_amount'])) {
                                $this->investments[$transaction['scheme_id']]['sold_amount'] += (float) $soldAmount;
                            } else {
                                $this->investments[$transaction['scheme_id']]['sold_amount'] = (float) $soldAmount;
                            }

                            if (isset($this->investments[$transaction['scheme_id']]['units'])) {
                                $this->investments[$transaction['scheme_id']]['units'] += (float) $transaction['units_bought'] - $soldUnits;
                            } else {
                                $this->investments[$transaction['scheme_id']]['units'] = (float) $transaction['units_bought'] - $soldUnits;
                            }
                        } else {
                            $diff = $this->helper->last($transaction['returns'])['total_return'] - $this->helper->first($transaction['returns'])['total_return'];
                            $transaction['diff'] = numberFormatPrecision((float) $diff, 2);

                            $transactionXirrDatesArr = [$this->helper->last($transaction['returns'])['date'], $transaction['date']];
                            $transactionXirrAmountsArr = [(float) $this->helper->last($transaction['returns'])['total_return'], (float) -$transaction['amount']];

                            $this->investments[$transaction['scheme_id']]['xirrDatesArr'] =
                                array_merge($this->investments[$transaction['scheme_id']]['xirrDatesArr'], $transactionXirrDatesArr);
                            $this->investments[$transaction['scheme_id']]['xirrAmountsArr'] =
                                array_merge($this->investments[$transaction['scheme_id']]['xirrAmountsArr'], $transactionXirrAmountsArr);

                            // array_push($this->investments[$transaction['scheme_id']]['xirrDatesArr'], $transaction['date']);
                            // array_push($this->investments[$transaction['scheme_id']]['xirrAmountsArr'], (float) -$transaction['amount']);
                            $transaction['available_amount'] = $transaction['amount'];
                            $transaction['units_sold'] = 0;


                            if (isset($this->investments[$transaction['scheme_id']]['amount'])) {
                                $this->investments[$transaction['scheme_id']]['amount'] += (float) $transaction['amount'];
                            } else {
                                $this->investments[$transaction['scheme_id']]['amount'] = (float) $transaction['amount'];
                            }

                            if (isset($this->investments[$transaction['scheme_id']]['sold_amount'])) {
                                $this->investments[$transaction['scheme_id']]['sold_amount'] += 0;
                            } else {
                                $this->investments[$transaction['scheme_id']]['sold_amount'] = 0;
                            }

                            if (isset($this->investments[$transaction['scheme_id']]['units'])) {
                                $this->investments[$transaction['scheme_id']]['units'] += (float) $transaction['units_bought'] - $transaction['units_sold'];
                            } else {
                                $this->investments[$transaction['scheme_id']]['units'] = (float) $transaction['units_bought'] - $transaction['units_sold'];
                            }
                        }
                        // trace([$transactionXirrAmountsArr, $transactionXirrDatesArr]);
                        $transaction['xirr'] =
                            numberFormatPrecision(
                                (float) NonPeriodic::rate(
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
                        $soldAmount = numberFormatPrecision((float) $soldAmount + $soldTransaction['amount'], 2);
                    }

                    if (isset($this->investments[$transaction['scheme_id']]['sold_amount'])) {
                        $this->investments[$transaction['scheme_id']]['sold_amount'] += $soldAmount;
                    } else {
                        $this->investments[$transaction['scheme_id']]['sold_amount'] = $soldAmount;
                    }

                    if (isset($this->investments[$transaction['scheme_id']]['units'])) {
                        $this->investments[$transaction['scheme_id']]['units'] += (float) $transaction['units_bought'] - $transaction['units_sold'];
                    } else {
                        $this->investments[$transaction['scheme_id']]['units'] = (float) $transaction['units_bought'] - $transaction['units_sold'];
                    }
                }

                // $performanceCounter++;
            }
            // trace([$this->investments]);
            if (!$timeline) {
                // $this->transactionsPackage->setFFValidation(false);
                // $this->transactionsPackage->update($transaction);
                try {
                    $this->localContent->write('.ff/sp/apps_fintech_mf_transactions/data/' . $transaction['id'] . '.json', $this->helper->encode($transaction));
                } catch (FilesystemException | UnableToWriteFile | \throwable $e) {
                    $this->addResponse($e->getMessage(), 1);

                    return false;
                }
            }

            // $this->basepackages->utils->setMicroTimer('transaction end - ' . $timeline->timelineDateBeingProcessed, true);
            // var_Dump($this->basepackages->utils->getMicroTimer());
            // $this->basepackages->utils->resetMicroTimer();
        }

        return true;
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
                // } else {
                //     if ($portfolioInvestmentArr['status'] === 'close') {
                //         unset($this->investments[$investmentAmficode]);
                //     }
                }
            }
        }
        // trace([$this->investments, $this->portfolio]);
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
            foreach ($this->investments as $schemeId => &$investment) {
                if (isset($this->portfolio['investments'][$schemeId])) {
                    $portfolioInvestment = $this->portfolio['investments'][$schemeId];
                    // trace([$investment]);
                    if (!$investment['open_transactions']) {
                        $portfolioInvestment['status'] = 'close';
                        $portfolioInvestment['amount'] = 0;
                        $portfolioInvestment['units'] = 0;
                        $portfolioInvestment['latest_value'] = 0;
                        $portfolioInvestment['sold_amount'] = $investment['sold_amount'];
                        $this->portfolio['sold_amount'] += $investment['sold_amount'];

                        $this->investmentsPackage->setFFValidation(false);
                        $this->investmentsPackage->update($portfolioInvestment);

                        continue;
                    }
                }

                if (!isset($portfolioInvestment['status']) ||
                    (isset($portfolioInvestment['status']) && $portfolioInvestment['status'] === 'open')
                ) {
                    $portfolioInvestment['start_date'] = $investment['start_date'];
                    $portfolioInvestment['sold_amount'] = $investment['sold_amount'];
                    $this->portfolio['sold_amount'] += $investment['sold_amount'];
                    $portfolioInvestment['status'] = 'open';
                    $portfolioInvestment['amc_id'] = $investment['amc_id'];
                    $portfolioInvestment['scheme_id'] = $investment['scheme_id'];
                    $portfolioInvestment['account_id'] = $this->portfolio['account_id'];
                    $portfolioInvestment['user_id'] = $this->portfolio['user_id'];
                    $portfolioInvestment['portfolio_id'] = $this->portfolio['id'];
                    $this->portfolio['invested_amount'] += $investment['total_investment'];
                    $portfolioInvestment['amount'] = numberFormatPrecision($investment['total_investment'], 2);
                    $portfolioInvestment['units'] = numberFormatPrecision($investment['units'], 3);
                    $portfolioInvestment['latest_nav'] = numberFormatPrecision($investment['latest_nav'], 2);
                    $portfolioInvestment['latest_value'] =
                        $this->investments[$schemeId]['latest_value'] =
                            numberFormatPrecision($portfolioInvestment['latest_nav'] * $portfolioInvestment['units'], 2);
                    $this->portfolio['return_amount'] += $portfolioInvestment['latest_value'];
                    $portfolioInvestment['latest_value_date'] = $investment['latest_nav_date'];

                    $portfolioInvestment['diff'] =
                        numberFormatPrecision(($portfolioInvestment['latest_value'] + $portfolioInvestment['sold_amount']) - $portfolioInvestment['amount'], 2);

                    // array_push($investment['xirrDatesArr'], $investment['latest_nav_date']);
                    // array_push($investment['xirrAmountsArr'], (float) $portfolioInvestment['latest_value']);

                    // trace([$investment['xirrAmountsArr'], $investment['xirrDatesArr']]);
                    $portfolioInvestment['xirr'] =
                        numberFormatPrecision(
                            (float) NonPeriodic::rate(
                                array_values($investment['xirrAmountsArr']),
                                array_values($investment['xirrDatesArr'])
                            ) * 100, 2
                        );

                    $this->portfolioXirrDatesArr = array_merge($this->portfolioXirrDatesArr, $investment['xirrDatesArr']);
                    $this->portfolioXirrAmountsArr = array_merge($this->portfolioXirrAmountsArr, $investment['xirrAmountsArr']);

                    if ($timeline) {
                        // trace([$timeline->timelineDateBeingProcessed, $this->portfolio['transactions'], $this->investments, $portfolioInvestment]);
                        $this->portfolio['investments'][$schemeId] =
                            array_replace($this->portfolio['investments'][$schemeId], $portfolioInvestment);

                        $investment['id'] = $portfolioInvestment['id'];
                    } else {
                        if (array_key_exists('id', $portfolioInvestment)) {
                            $this->investmentsPackage->setFFValidation(false);
                            $this->investmentsPackage->update($portfolioInvestment);
                        } else {
                            $this->investmentsPackage->add($portfolioInvestment);
                        }

                        $investment['id'] = $this->investmentsPackage->packagesData->last['id'];
                    }
                } else {
                    $this->portfolio['sold_amount'] += $investment['sold_amount'];
                    $this->portfolio['invested_amount'] += $investment['total_investment'];
                    $this->portfolio['return_amount'] += $portfolioInvestment['latest_value'];
                }
            }
            // trace([$this->portfolio]);
            //Running loop again to recalculate category percentage. We need to calculate the portfolio total in order to get percentage of categories.
            unset($investment);//Unset as we are using the same var $investment again.
            foreach ($this->investments as $schemeId => $investment) {
                if (isset($this->portfolio['investments'][$schemeId]) &&
                    $this->portfolio['investments'][$schemeId]['status'] === 'close'
                ) {
                    continue;
                }

                if (!isset($investment['scheme_id'])) {
                    continue;
                }

                if (isset($this->schemes[$schemeId])) {
                    $scheme = &$this->schemes[$schemeId];
                } else {
                    $this->schemes[$schemeId] = $this->schemesPackage->getSchemeById($investment['scheme_id']);

                    $scheme = &$this->schemes[$transaction['scheme_id']];
                }
                // $scheme = $this->schemesPackage->getSchemeById($investment['scheme_id']);

                if (!isset($this->portfolio['allocation']['by_schemes'][$scheme['id']])) {
                    $this->portfolio['allocation']['by_schemes'][$scheme['id']] = [];
                }

                $schemeAllocation = &$this->portfolio['allocation']['by_schemes'][$scheme['id']];
                $schemeAllocation['scheme_id'] = $scheme['id'];
                $schemeAllocation['scheme_name'] = $scheme['name'];
                $schemeAllocation['invested_amount'] = $investment['total_investment'];
                // trace([$investment['total_investment'], $this->portfolio['invested_amount']]);
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
                        $this->portfolio['allocation']['by_subcategories'][$scheme['category_id']]['category']['parent_id'] = $parentCategory['category'];

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

        // if ($this->portfolio['sold_amount'] > 0) {
        //     $investedAmount = $this->portfolio['invested_amount'] + abs($this->portfolio['invested_amount'] - $this->portfolio['sold_amount']);
        // } else {
        //     $investedAmount = $this->portfolio['invested_amount'];
        // }

        $this->portfolio['profit_loss'] =
            numberFormatPrecision(($this->portfolio['return_amount'] + $this->portfolio['sold_amount']) - $this->portfolio['invested_amount'], 2);
            // trace([$this->portfolio['profit_loss']]);
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
                    (float) NonPeriodic::rate(
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

        return true;
    }

    protected function switchModel($model = null)
    {
        if (!$model) {
            $this->setModelToUse($this->modelToUse = AppsFintechMfPortfolios::class);

            $this->packageName = 'mfportfolios';
        } else {
            $this->setModelToUse($model);
        }

        if ($this->config->databasetype !== 'db') {
            $this->ffStore = null;
        }
    }

    // protected function processPortfolioPerformances()
    // {
    //     $performances = [];

    //     foreach ($this->portfolio['performances']['performances'] as $performance) {
    //         $performances[$performance['date']] =
    //             [
    //                 'date'             => $performance['date'],
    //                 'total_investment' => $performance['total_investment'],
    //             ];
    //     }

    //     unset($this->portfolio['performances']['performances']);

    //     $performances = $this->fillPerformancesDateGaps($performances);

    //     $this->portfolio['performances']['performances_chunks'] = $this->createChunks($performances);

    //     $this->switchModel(AppsFintechMfPortfoliosPerformancesChunks::class);

    //     $this->update($this->portfolio['performances']);

    //     $this->switchModel();
    // }

    // protected function fillPerformancesDateGaps($performances)
    // {
    //     $firstDate = \Carbon\Carbon::parse($this->helper->firstKey($performances));
    //     $lastDate = \Carbon\Carbon::today();

    //     $numberOfDays = $firstDate->diffInDays($lastDate) + 1;//Include last day in calculation

    //     if ($numberOfDays != count($performances)) {
    //         $performances = array_values($performances);

    //         $totalInvestmentArr = [];

    //         foreach ($performances as $performanceKey => $performance) {
    //             $totalInvestmentArr[$performance['date']] = $performance;

    //             $differenceDays = 0;

    //             if (isset($performances[$performanceKey + 1])) {
    //                 $currentDate = \Carbon\Carbon::parse($performance['date']);
    //                 $nextDate = \Carbon\Carbon::parse($performances[$performanceKey + 1]['date']);
    //                 $differenceDays = $currentDate->diffInDays($nextDate);
    //             } else {
    //                 $currentDate = \Carbon\Carbon::parse($performance['date']);
    //                 $nextDate = \Carbon\Carbon::today();
    //                 $differenceDays = $currentDate->diffInDays($nextDate) + 1;
    //             }

    //             if ($differenceDays > 1) {
    //                 for ($days = 1; $days < $differenceDays; $days++) {
    //                     $missingDay = $currentDate->addDay(1)->toDateString();

    //                     if (!isset($totalInvestmentArr[$missingDay])) {
    //                         $performance['date'] = $missingDay;

    //                         $totalInvestmentArr[$performance['date']] = $performance;
    //                     }
    //                 }
    //             }
    //         }

    //         if ($numberOfDays != count($totalInvestmentArr)) {
    //             throw new \Exception('Cannot process performance missing dates correctly.');
    //         }

    //         return $totalInvestmentArr;
    //     }

    //     return $performances;
    // }

    // protected function createChunks($performances)
    // {
    //     $chunks = [];

    //     $datesKeys = array_keys($performances);

    //     foreach (['week', 'month', 'threeMonth', 'sixMonth', 'year', 'threeYear', 'fiveYear', 'tenYear'] as $time) {
    //         $latestDate = \Carbon\Carbon::parse($this->helper->lastKey($performances));
    //         $timeDate = null;

    //         if ($time === 'week') {
    //             $timeDate = $latestDate->subDay(6)->toDateString();
    //         } else if ($time === 'month') {
    //             $timeDate = $latestDate->subMonth()->toDateString();
    //         } else if ($time === 'threeMonth') {
    //             $timeDate = $latestDate->subMonth(3)->toDateString();
    //         } else if ($time === 'sixMonth') {
    //             $timeDate = $latestDate->subMonth(6)->toDateString();
    //         } else if ($time === 'year') {
    //             $timeDate = $latestDate->subYear()->toDateString();
    //         } else if ($time === 'threeYear') {
    //             $timeDate = $latestDate->subYear(3)->toDateString();
    //         } else if ($time === 'fiveYear') {
    //             $timeDate = $latestDate->subYear(5)->toDateString();
    //         } else if ($time === 'tenYear') {
    //             $timeDate = $latestDate->subYear(10)->toDateString();
    //         }

    //         if (isset($performances[$timeDate])) {
    //             $timeDateKey = array_search($timeDate, $datesKeys);
    //             $timeDateChunks = array_slice($performances, $timeDateKey);

    //             if (count($timeDateChunks) > 0) {
    //                 $chunks[$time] = [];

    //                 foreach ($timeDateChunks as $timeDateChunkDate => $timeDateChunk) {
    //                     $chunks[$time][$timeDateChunkDate] = [];
    //                     $chunks[$time][$timeDateChunkDate]['date'] = $timeDateChunk['date'];
    //                     $chunks[$time][$timeDateChunkDate]['total_investment'] = $timeDateChunk['total_investment'];
    //                     // $chunks[$time][$timeDateChunkDate]['diff'] =
    //                     //     numberFormatPrecision($timeDateChunk['nav'] - $this->helper->first($timeDateChunks)['nav'], 4);
    //                     // $chunks[$time][$timeDateChunkDate]['diff_percent'] =
    //                     //     numberFormatPrecision(($timeDateChunk['nav'] * 100 / $this->helper->first($timeDateChunks)['nav'] - 100), 2);
    //                 }
    //             }
    //         }
    //     }

    //     return $chunks;
    // }
}