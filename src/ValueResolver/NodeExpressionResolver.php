<?php
/**
 * Parser Reflection API
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ParserReflection\ValueResolver;

use ParserReflection\ReflectionFileNamespace;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;

/**
 * Tries to resolve expression into value
 */
class NodeExpressionResolver
{

    /**
     * Name of the constant (if present)
     *
     * @var null|string
     */
    private $constantName = null;

    /**
     * Current reflection context for parsing
     *
     * @var mixed
     */
    private $context;

    /**
     * Flag if expression is constant
     *
     * @var bool
     */
    private $isConstant = false;

    /**
     * @var mixed Value of expression/constant
     */
    private $value;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function getConstantName()
    {
        return $this->constantName;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isConstant()
    {
        return $this->isConstant;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Node $node)
    {
        $this->value = $this->resolve($node);
    }

    /**
     * Resolves node into valid value
     *
     * @param Node $node
     *
     * @return mixed
     */
    protected function resolve(Node $node)
    {
        $nodeType   = $node->getType();
        $methodName = 'resolve' . $nodeType;
        if (method_exists($this, $methodName)) {
            return $this->$methodName($node);
        }

        return null;
    }

    protected function resolveScalar_DNumber(Scalar\DNumber $node)
    {
        return $node->value;
    }

    protected function resolveScalar_LNumber(Scalar\LNumber $node)
    {
        return $node->value;
    }

    protected function resolveScalar_String(Scalar\String_ $node)
    {
        return $node->value;
    }

    protected function resolveScalar_MagicConst_Method(MagicConst\Method $node)
    {
        if ($this->context instanceof \ReflectionMethod) {
            $fullName = $this->context->getDeclaringClass()->getName() . '::' . $this->context->getShortName();

            return $fullName;
        }

        return '';
    }

    protected function resolveScalar_MagicConst_Function(MagicConst\Function_ $node)
    {
        if ($this->context instanceof \ReflectionFunctionAbstract) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveScalar_MagicConst_Namespace(MagicConst\Namespace_ $node)
    {
        if (method_exists($this->context, 'getNamespaceName')) {
            return $this->context->getNamespaceName();
        }

        return '';
    }

    protected function resolveScalar_MagicConst_Class(MagicConst\Class_ $node)
    {
        if ($this->context instanceof \ReflectionClass) {
            return $this->context->getName();
        }
        if (method_exists($this->context, 'getDeclaringClass')) {
            return $this->context->getDeclaringClass()->getName();
        }

        return '';
    }

    protected function resolveScalar_MagicConst_Dir(MagicConst\Dir $node)
    {
        if (method_exists($this->context, 'getFileName')) {
            return dirname($this->context->getFileName());
        }

        return '';
    }

    protected function resolveScalar_MagicConst_File(MagicConst\File $node)
    {
        if (method_exists($this->context, 'getFileName')) {
            return $this->context->getFileName();
        }

        return '';
    }

    protected function resolveScalar_MagicConst_Line(MagicConst\Line $node)
    {
        return $node->hasAttribute('startLine') ? $node->getAttribute('startLine') : 0;
    }

    protected function resolveScalar_MagicConst_Trait(MagicConst\Trait_ $node)
    {
        if ($this->context instanceof \ReflectionClass && $this->context->isTrait()) {
            return $this->context->getName();
        }

        return '';
    }

    protected function resolveExpr_ConstFetch(Expr\ConstFetch $node)
    {
        /** @var ReflectionFileNamespace|null $fileNamespace */
        $fileNamespace = null;
        $isFQNConstant = $node->name instanceof Node\Name\FullyQualified;
        $constantName  = $node->name->toString();
        if (!$isFQNConstant) {
            if (method_exists($this->context, 'getFileNamespace')) {
                $fileNamespace = $this->context->getFileNamespace();
                if ($fileNamespace->hasConstant($constantName)) {
                    return $fileNamespace->getConstant($constantName);
                }
            }
        }

        if (defined($constantName)) {
            return constant($constantName);
        }

        return null;
    }

    protected function resolveExpr_Array(Expr\Array_ $node)
    {
        $result = [];
        foreach ($node->items as $itemIndex => $arrayItem) {
            $itemValue = $this->resolve($arrayItem->value);
            $itemKey   = isset($arrayItem->key) ? $this->resolve($arrayItem->key) : $itemIndex;
            $result[$itemKey] = $itemValue;
        }

        return $result;
    }
}
