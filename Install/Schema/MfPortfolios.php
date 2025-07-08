<?php

namespace Apps\Fintech\Packages\Mf\Portfolios\Install\Schema;

use Phalcon\Db\Column;
use Phalcon\Db\Index;

class MfPortfolios
{
    public function columns()
    {
        return
        [
           'columns' => [
                new Column(
                    'id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                        'autoIncrement' => true,
                        'primary'       => true,
                    ]
                ),
                new Column(
                    'name',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 100,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'description',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 4096,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'account_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'user_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'invested_amount',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'return_amount',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'sold_amount',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'profit_loss',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'total_value',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'xirr',
                    [
                        'type'          => Column::TYPE_FLOAT,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'strategy_ids',
                    [
                        'type'          => Column::TYPE_JSON,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'allocation',
                    [
                        'type'          => Column::TYPE_JSON,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'status',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 10,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'start_date',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 15,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'is_clone',
                    [
                        'type'          => Column::TYPE_BOOLEAN,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'investment_source',//virtual, balances, accounting
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 15,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'book_id',
                    [
                        'type'          => Column::TYPE_SMALLINTEGER,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'withdraw_bankaccount_id',
                    [
                        'type'          => Column::TYPE_SMALLINTEGER,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'deposit_bankaccount_id',
                    [
                        'type'          => Column::TYPE_SMALLINTEGER,
                        'notNull'       => false,
                    ]
                ),
            ],
            'indexes' => [
                new Index(
                    'column_UNIQUE',
                    [
                        'name'
                    ],
                    'UNIQUE'
                )
            ],
            'options' => [
                'TABLE_COLLATION' => 'utf8mb4_general_ci'
            ]
        ];
    }

    public function indexes()
    {
        return
        [
            new Index(
                'column_INDEX',
                [
                    'name',
                    'user_id',
                    'account_id'
                ],
                'INDEX'
            )
        ];
    }
}
