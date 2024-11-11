<?php
/**
 * Author: Zane
 * Email: 873934580@qq.com
 * Date: 2024/11/11
 */

it("解析-数字", function () {
    $str = '(limit=10, limit1="123")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('limit'))->toEqual(10)
        ->and($result->getAttribute('limit1'))->toEqual("123");
})->group("parser");

it("解析-布尔值", function () {
    $str = '(param=false, param1=true, param2="false", param3="true", param4)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->getAttribute('param'))->toBeFalse()
        ->and($result->getAttribute("param1"))->toBeTrue()
        ->and($result->getAttribute("param2"))->toBeFalse()
        ->and($result->getAttribute("param3"))->toBeTrue()
        ->and($result->getAttribute("param4"))->toBeTrue();
})->group("parser");

it("解析-字符串", function () {
    $str = '(param=测试字符串, param3=\'测试"字符串\', param1="测试,字符串", param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('测试,字符串')
        ->and($result->getAttribute("param3"))->toEqual('测试"字符串')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("解析-字符串-复杂模式", function () {
    $str = '(param="测试字符串", param1="测试\',字符串", param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('测试\',字符串')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("解析-字符串-无符号", function () {
    $str = '(param=测试字符串, param1="测试\',字符串", param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('测试\',字符串')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("解析-字符串+变量", function () {
    $str = '(param="测试字符串", param1=$filed, param2="@#测%试&,字()符&串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);

    expect($result->getAttribute('param'))->toEqual("测试字符串")
        ->and($result->getAttribute("param1"))->toEqual('$filed')
        ->and($result->getAttribute("param2"))->toEqual('@#测%试&,字()符&串');
})->group("parser");

it("解析-数组", function () {
    $str = '(limit=[1,2,3,4])';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('limit'))->toEqual("[1,2,3,4]");

})->group("parser");

it("解析-数组+字符串", function () {
    $str = '(limit=[1,2,3,4], param="测试字符串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('limit'))->toEqual("[1,2,3,4]")
        ->and($result->getAttribute("param"))->toEqual("测试字符串");

})->group("parser");

it("解析-变量", function () {
    $str = '(param=$field)';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field');

})->group("parser");

it("解析-变量+数组", function () {
    $str = '(param=$field, param1=["aa" => $cc, "bb" => $dd])';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field')
        ->and($result->getAttribute("param1"))->toEqual('["aa" => $cc, "bb" => $dd]');

})->group("parser");

it("解析-变量+字符串", function () {
    $str = '(param=$field, param1="测试字符串")';
    $result = \PTAdmin\Addon\Compiler\Parser::make($str);
    expect($result->getAttribute('param'))->toEqual('$field')
        ->and($result->getAttribute("param1"))->toEqual("测试字符串");

})->group("parser");

it("解析-混合模式", function () {
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