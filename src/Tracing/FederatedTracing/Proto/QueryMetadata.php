<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: src/Tracing/FederatedTracing/reports.proto

namespace Nuwave\Lighthouse\Tracing\FederatedTracing\Proto;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>QueryMetadata</code>
 */
class QueryMetadata extends \Google\Protobuf\Internal\Message
{
    /**
     * The operation name. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *
     * Generated from protobuf field <code>string name = 1 [json_name = "name"];</code>
     */
    protected $name = '';
    /**
     * the operation signature. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *
     * Generated from protobuf field <code>string signature = 2 [json_name = "signature"];</code>
     */
    protected $signature = '';
    /**
     * (Optional) Persisted query ID that was used to request this operation.
     *
     * Generated from protobuf field <code>string pq_id = 3 [json_name = "pqId"];</code>
     */
    protected $pq_id = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $name
     *           The operation name. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *     @type string $signature
     *           the operation signature. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *     @type string $pq_id
     *           (Optional) Persisted query ID that was used to request this operation.
     * }
     */
    public function __construct($data = NULL) {
        \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * The operation name. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *
     * Generated from protobuf field <code>string name = 1 [json_name = "name"];</code>
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * The operation name. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *
     * Generated from protobuf field <code>string name = 1 [json_name = "name"];</code>
     * @param string $var
     * @return $this
     */
    public function setName($var)
    {
        GPBUtil::checkString($var, True);
        $this->name = $var;

        return $this;
    }

    /**
     * the operation signature. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *
     * Generated from protobuf field <code>string signature = 2 [json_name = "signature"];</code>
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * the operation signature. For operations with a PQ ID as the stats report key, either name or signature must be present in the metadata.
     *
     * Generated from protobuf field <code>string signature = 2 [json_name = "signature"];</code>
     * @param string $var
     * @return $this
     */
    public function setSignature($var)
    {
        GPBUtil::checkString($var, True);
        $this->signature = $var;

        return $this;
    }

    /**
     * (Optional) Persisted query ID that was used to request this operation.
     *
     * Generated from protobuf field <code>string pq_id = 3 [json_name = "pqId"];</code>
     * @return string
     */
    public function getPqId()
    {
        return $this->pq_id;
    }

    /**
     * (Optional) Persisted query ID that was used to request this operation.
     *
     * Generated from protobuf field <code>string pq_id = 3 [json_name = "pqId"];</code>
     * @param string $var
     * @return $this
     */
    public function setPqId($var)
    {
        GPBUtil::checkString($var, True);
        $this->pq_id = $var;

        return $this;
    }

}

