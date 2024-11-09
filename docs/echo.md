# 输出内容
> 使用 ``{ }`` 输出内容到页面中，如: `{$field}`。
- 注:需要区分js相关语法，需注意以下几点： 
  - `{` 后不支持换行符。
  - `}` 前不支持换行符。 
  - `{$field}` 变量名称必须以 `$` 符号开始。 
  - 以下均为无效写法
  ```html
  以下示例均无效，{} 存在换行符。
  {
    $field
  }
  以下示例均无效，缺少$符号
  {field}
  ```
  
## 支持的调用方式
### 1、基础调用
- 语法：`{$field}`
- 示例：
```php
Hello, {$field}
```

### 2、默认值
- 语法：`{$field|'默认值'}`, 同时默认值支持以php变量方式书写，例如：`{$field|$default}`
- 示例：
```php
# 直接写默认值
{$field|'默认值'}

# 默认值为变量
$default = "我是默认值";
{$field.title|$default}
```
### 3、使用函数方式调用
- 语法：`{$field(default=123, limit=10, pl="...")}`
- 说明：当同时出现 `|` 符号时，以 `default` 参数优先。
- 参数
  - default: 
    - 说明：默认值，默认为空。
    - 默认值：空
  - func:
    - 说明：调用函数，默认为空。支持使用函数，例如：`func='strtoupper'`。
    - 默认值：空
    - 示例：`{$field(default=123, limit=10, pl="...", func="strtoupper")}` 
  - limit: 
    - 说明：限制字符长度，默认为空。
    - 默认值：空
  - pl: 
    - 说明：当 `limit` 参数不为空时，超出字符显示。
    - 默认值：`...`。
  - format_before: 
    - 说明：需要实现函数 `date_format`, 当输出内容为一个有效的时间日期格式时，支持的输出方式，例如：`Y-m-d H:i:s`。
    - 默认值：空。
  - format_number:
  - format_money:
  - format_date:
  - format_size:

### 4、使用 `.` 嵌套调用
- 语法：`{$field.title}`
- 说明：当 `field` 为数组时，支持使用 `.` 嵌套调用。
- 示例：
  ```php
    $field = [ 'field' => [ 'title' => '我是标题']]
  
    {$field.field.title}
  ```

### 5、显示非转义字符
- 语法：`{:$field}`
- 说明：默认情况下输出的内容会自动使用`htmlspecialchars`函数进行转义，以防范XSS攻击，可以使用 `{:$field}` 取消转义。

### 5、取消编译
- 语法：`@{$field}`
- 说明：当需要输出原始的字符串时，可以使用 `@` 符号取消编译。