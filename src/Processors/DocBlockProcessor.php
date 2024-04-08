<?php

namespace AndreasElia\PostmanGenerator\Processors;

use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

class DocBlockProcessor
{
    public function __invoke(ReflectionMethod|ReflectionFunction $reflectionMethod): string
    {
        try {
            $lexer = new Lexer;
            $constExprParser = new ConstExprParser;
            $parser = new PhpDocParser(new TypeParser($constExprParser), $constExprParser);

            $description = '';
            $comment = $reflectionMethod->getDocComment();
            $tokens = new TokenIterator($lexer->tokenize($comment));
            $phpDocNode = $parser->parse($tokens);
            $tags = [];
            foreach ($phpDocNode->children as $child) {
                if ($child instanceof PhpDocTextNode) {
                    $description .= ' '.$child->text;
                }if ($child instanceof PhpDocTagNode) {
                    //print_r(json_encode($child, 128));
                    $tags[] = [
                        'type' => $child->name,
                        'class' => $this->getTagClass($child)
                    ];
                }
            }
            $description = Str::squish($description);
            foreach ($tags as $tag) {
                $description .= "\n";
                $description .= ''.$tag['type'] . ' - ' . $tag['class'];
            }
            print_r($description);
            print_r("\n");
            return $description;
        } catch (Throwable $e) {
            print_r($e->getMessage());
            return '';
        }
    }
    private function getTagClass($child)
    {
        if (isset($child->type)) {
            $typesResult = '';
            foreach ($child->type->types as $type) {
                $typesResult .= $type->name . '|';
            }
            return $typesResult;
        }
        return $child->value;
    }
}
