<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

it("Parser number", function () {
    $str = '(limit=10, limit1="123")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('limit'))->toEqual(10)
        ->and($result->getAttribute('limit1'))->toEqual("123");
})->group("parser");

it("Parser bool", function () {
    $str = '(param=false, param1=true, param2="false", param3="true", param4)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->getAttribute('param'))->toBeFalse()
        ->and($result->getAttribute("param1"))->toBeTrue()
        ->and($result->getAttribute("param2"))->toBeFalse()
        ->and($result->getAttribute("param3"))->toBeTrue()
        ->and($result->getAttribute("param4"))->toBeTrue();
})->group("parser");

it("Parser string", function () {
    $str = '(param=测试字符串, param3=\'测试"字符串\', param1="测试,字符串", param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('测试,字符串')
        ->and($result->getAttribute("param3"))->toEqual('测试"字符串')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("Parser string complex pattern", function () {
    $str = '(param="测试字符串", param1="测试\',字符串", param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('测试\',字符串')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("Parser string Unsigned", function () {
    $str = '(param=测试字符串, param1="测试\',字符串", param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('测试\',字符串')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("Parser String and Var", function () {
    $str = '(param="测试字符串", param1=$filed, param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('$filed')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("Parser array", function () {
    $str = '(limit=[1,2,3,4])';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('limit'))->toEqual("[1,2,3,4]");

})->group("parser");

it("Parser array and string", function () {
    $str = '(limit=[1,2,3,4], param="测试字符串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('limit'))->toEqual("[1,2,3,4]")
        ->and($result->getAttribute("param"))->toEqual("测试字符串");

})->group("parser");

it("Parser var", function () {
    $str = '(param=$field)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field');

})->group("parser");

it("Parser var and array", function () {
    $str = '(param=$field, param1=["aa" => $cc, "bb" => $dd])';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field')
        ->and($result->getAttribute("param1"))->toEqual('["aa" => $cc, "bb" => $dd]');

})->group("parser");

it("Parser var and string", function () {
    $str = '(param=$field, param1="测试字符串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field')
        ->and($result->getAttribute("param1"))->toEqual("测试字符串");

})->group("parser");

it("Parser mix", function () {
    $str = '(param=$field, param1="测试字符串", param2=[1,2,3,4],  param7="[1,2,3,4]", param3=false, param6 = "ddliuoujomvf&&9338*", param4, param5=true)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field')
        ->and($result->getAttribute("param2"))->toEqual("[1,2,3,4]")
        ->and($result->getAttribute("param7"))->toEqual("[1,2,3,4]")
        ->and($result->getAttribute("param6"))->toEqual("ddliuoujomvf&&9338*")
        ->and($result->getAttribute("param3"))->toBeFalse()
        ->and($result->getAttribute("param4"))->toBeTrue()
        ->and($result->getAttribute("param5"))->toBeTrue()
        ->and($result->getAttribute("param1"))->toEqual("测试字符串");
})->group("parser");

it("Parser output assignment", function () {
    $str = '(limit=2, out=field)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->isOutput())->toBeTrue()
        ->and($result->getOutput())->toEqual('$field')
        ->and($result->getExpression())->toEqual("\\PTAdmin\\Addon\\Service\\DirectivesDTO::build(['limit' => '2', 'out' => 'field'])");
})->group("parser");

it("Parser output uses iteration when out is true", function () {
    $str = '(limit=2, id=item, out=true)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->isOutput())->toBeTrue()
        ->and($result->getIteration())->toEqual('$item')
        ->and($result->getOutput())->toEqual('$item');
})->group("parser");

it("Parser excludes id and empty from expression payload", function () {
    $str = '(id=item, empty="暂无数据", limit=2)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->toArray())->toEqual([
        'limit' => '2',
    ])->and($result->getExpression())->toEqual("\\PTAdmin\\Addon\\Service\\DirectivesDTO::build(['limit' => '2'])");
})->group("parser");

it("Parser empty supports variable output", function () {
    $str = '(empty=$emptyText)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->getEmpty())->toEqual('$emptyText');
})->group("parser");

it("Parser defaults iteration variable to field", function () {
    $str = '(limit=2)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->getIteration())->toEqual('$field');
})->group("parser");

it("Parser validates id format", function () {
    $str = '(id=1item, limit=2)';

    expect(fn () => \PTAdmin\Addon\Compiler\Parser::make($str))
        ->toThrow(\PTAdmin\Addon\Exception\DirectivesException::class, 'id');
})->group("parser");
