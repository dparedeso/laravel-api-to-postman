<?php

namespace AndreasElia\PostmanGenerator\Processors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionParameter;

class FormDataProcessor
{
    public function process($reflectionMethod): Collection
    {
        /** @var ReflectionParameter $rulesParameter */
        $rulesParameter = collect($reflectionMethod->getParameters())
            ->first(function ($value) {
                $value = $value->getType();
                return $value && is_subclass_of($value->getName(), FormRequest::class);
            });
        if ($rulesParameter) {
            /** @var FormRequest $class */
            $class = new ($rulesParameter->getType()->getName());

            $classRules = method_exists($class, 'rules') ? $class->rules() : [];

            foreach ($classRules as $fieldName => $rule) {
                if (is_string($rule)) {
                    $rule = preg_split('/\s*\|\s*/', $rule);
                }

                $printRules = config('api-postman.print_rules');

                $rules->push([
                    'name' => $fieldName,
                    'description' => $printRules ? $rule : '',
                ]);

                if (is_array($rule) && in_array('confirmed', $rule)) {
                    $rules->push([
                        'name' => $fieldName.'_confirmation',
                        'description' => $printRules ? $rule : '',
                    ]);
                }
            }
        }
        return $rules;
    }
    public function getFormData($reflectionMethod, $method)
    {
        $children = $this->getChildren($reflectionMethod);
        $data = [];
        foreach ($children as $child) {
            if ($child instanceof PhpDocTagNode) {
                if ($child->name === '@example') {
                    $rawData = $child->value->value;
                    $datum = explode(' ', $rawData);
                    $key = array_shift($datum);
                    $value = array_shift($datum);
                    $description = '';
                    if (!empty($datum)) {
                        $description = implode(' ', $datum);
                    }
                        $data[] = [
                            'key' => $key,
                            'value' => $value,
                            'disabled' => false,
                            'description' => $description
                        ];
                    }
                }
            }
        return collect($data);
    }

    private function getChildren($reflectionMethod)
    {
        $lexer = new Lexer;
        $constExprParser = new ConstExprParser;
        $parser = new PhpDocParser(new TypeParser($constExprParser), $constExprParser);

        $comment = $reflectionMethod->getDocComment();
        if (empty($comment)) {
            return array();
        }
        $tokens = new TokenIterator($lexer->tokenize($comment));
        $phpDocNode = $parser->parse($tokens);
        return $phpDocNode->children;
    }
}
