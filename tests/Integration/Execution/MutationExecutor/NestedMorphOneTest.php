<?php

namespace Tests\Integration\Execution\MutationExecutor;

use Tests\DBTestCase;
use Tests\Utils\Models\Invoice;
use Tests\Utils\Models\Payment;

class NestedMorphOneTest extends DBTestCase
{

    protected string $schema = /** @lang GraphQL */ '
        type Invoice {
            id: ID!
            lineItems: [PayableLineItem!]! @morphMany
        }

        type PayableLineItem {
            id: ID!
            fulfills: Payment @morphOne
        }

        type Payment  {
            id: ID!
            fulfilledBy: PayableLineItem @morphTo
        }

        extend type Mutation {
            updateInvoice(id: ID! @eq, input: UpdateInvoiceInput! @spread): Invoice!
                @update
        }

        input UpdateInvoiceInput {
            lineItems: UpdatePayableLineItemInput
        }

        input UpdatePayableLineItemInput {
            upsert: [UpsertPayableLineItemInput!]!
            delete: [ID!]
        }

        input UpsertPayableLineItemInput {
            id: ID
            fulfills: PayableLineItemFulfilledByInput
        }

        input PayableLineItemFulfilledByInput {
            update: PayableLineItemFulfilledByUpdateInput!
        }

        input PayableLineItemFulfilledByUpdateInput {
            id: ID!
        }
    ' . self::PLACEHOLDER_QUERY;


    public function testDeeplyNestedMorphOne(): void
    {
        $invoice = new Invoice();
        $invoice->save();

        $payment = new Payment();
        $payment->save();

        $this->graphQL(/** @lang GraphQL */ '
        mutation {
            updateInvoice(
                id: 1
                input: {
                    lineItems: {
                        upsert: [
                            {
                                id: 1
                                fulfills: {
                                    update: {
                                        id: 1
                                    }
                                }
                            }
                        ]
                    }
                }
            ) {
                id
                lineItems {
                    id
                    fulfills {
                        fulfilledBy {
                            id
                        }
                    }
                }
            }
        }
        ')->assertJson([
            'data' => [
                'updateInvoice' => [
                    'id' => $invoice->id,
                    'lineItems' => [
                        [
                            'fulfills' => [
                                'id' => $payment->id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
