<?php

namespace AppVerk\ApiTestCasesBundle\Api\Cases;

use Coduo\PHPMatcher\Lexer;
use Coduo\PHPMatcher\Matcher;
use Coduo\PHPMatcher\Parser;
use Coduo\PHPMatcher\Backtrace;

class MatcherFactory
{
    /**
     * @return Matcher
     */
    public static function buildXmlMatcher()
    {
        return self::buildMatcher(Matcher\XmlMatcher::class);
    }

    /**
     * @param string $matcherClass
     *
     * @return Matcher
     */
    protected static function buildMatcher($matcherClass)
    {
        $orMatcher = self::buildOrMatcher();
        $chainMatcher = new Matcher\ChainMatcher(
            'chain',
            new Backtrace\InMemoryBacktrace(),
            [
                new $matcherClass($orMatcher, new Backtrace\InMemoryBacktrace()),
            ]
        );

        return new Matcher($chainMatcher, new Backtrace\InMemoryBacktrace());
    }

    /**
     * @return Matcher\ChainMatcher
     */
    protected static function buildOrMatcher()
    {
        $scalarMatchers = self::buildScalarMatchers();
        $orMatcher = new Matcher\OrMatcher(new Backtrace\InMemoryBacktrace(), $scalarMatchers);
        $arrayMatcher = new Matcher\ArrayMatcher(
            new Matcher\ChainMatcher(
                'chain',
                new Backtrace\InMemoryBacktrace(),
                [
                    $orMatcher,
                    $scalarMatchers,
                ]
            ),
            new Backtrace\InMemoryBacktrace(),
            self::buildParser()
        );
        $chainMatcher = new Matcher\ChainMatcher(
            'chain',
            new Backtrace\InMemoryBacktrace(),
            [
                $orMatcher,
                $arrayMatcher,
            ]
        );

        return $arrayMatcher;
    }

    /**
     * @return Matcher\ChainMatcher
     */
    protected static function buildScalarMatchers()
    {
        $parser = self::buildParser();
        $InMemoryBacktrace = new Backtrace\InMemoryBacktrace();

        return new Matcher\ChainMatcher(
            'chain',
            $InMemoryBacktrace,
            [
                new Matcher\CallbackMatcher($InMemoryBacktrace),
                new Matcher\ExpressionMatcher($InMemoryBacktrace),
                new Matcher\NullMatcher($InMemoryBacktrace),
                new Matcher\StringMatcher($InMemoryBacktrace, $parser),
                new Matcher\IntegerMatcher($InMemoryBacktrace, $parser),
                new Matcher\BooleanMatcher($InMemoryBacktrace, $parser),
                new Matcher\DoubleMatcher($InMemoryBacktrace, $parser),
                new Matcher\NumberMatcher($InMemoryBacktrace, $parser),
                new Matcher\ScalarMatcher($InMemoryBacktrace),
                new Matcher\WildcardMatcher($InMemoryBacktrace),
            ]
        );
    }

    /**
     * @return Parser
     */
    protected static function buildParser()
    {
        return new Parser(new Lexer(), new Parser\ExpanderInitializer(new Backtrace\InMemoryBacktrace()));
    }

    /**
     * @return Matcher
     */
    public static function buildJsonMatcher()
    {
        return self::buildMatcher(Matcher\JsonMatcher::class);
    }
}
