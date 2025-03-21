<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: src/Tracing/FederatedTracing/reports.proto

namespace Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * This node represents a way to reach into the response path and attach related entities.
 * XXX Flatten is really not the right name and this node may be renamed in the query planner.
 *
 * Generated from protobuf message <code>Trace.QueryPlanNode.FlattenNode</code>
 */
class FlattenNode extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>repeated .Trace.QueryPlanNode.ResponsePathElement response_path = 1 [json_name = "responsePath"];</code>
     */
    private $response_path;
    /**
     * Generated from protobuf field <code>.Trace.QueryPlanNode node = 2 [json_name = "node"];</code>
     */
    protected $node = null;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type array<\Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode\ResponsePathElement>|\Google\Protobuf\Internal\RepeatedField $response_path
     *     @type \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode $node
     * }
     */
    public function __construct($data = NULL) {
        \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Metadata\Reports::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>repeated .Trace.QueryPlanNode.ResponsePathElement response_path = 1 [json_name = "responsePath"];</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getResponsePath()
    {
        return $this->response_path;
    }

    /**
     * Generated from protobuf field <code>repeated .Trace.QueryPlanNode.ResponsePathElement response_path = 1 [json_name = "responsePath"];</code>
     * @param array<\Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode\ResponsePathElement>|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setResponsePath($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::MESSAGE, \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode\ResponsePathElement::class);
        $this->response_path = $arr;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.Trace.QueryPlanNode node = 2 [json_name = "node"];</code>
     * @return \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode|null
     */
    public function getNode()
    {
        return $this->node;
    }

    public function hasNode()
    {
        return isset($this->node);
    }

    public function clearNode()
    {
        unset($this->node);
    }

    /**
     * Generated from protobuf field <code>.Trace.QueryPlanNode node = 2 [json_name = "node"];</code>
     * @param \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode $var
     * @return $this
     */
    public function setNode($var)
    {
        GPBUtil::checkMessage($var, \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace\QueryPlanNode::class);
        $this->node = $var;

        return $this;
    }

}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(FlattenNode::class, \Nuwave\Lighthouse\Tracing\FederatedTracing\Proto\Trace_QueryPlanNode_FlattenNode::class);

