# AI Check - Assignment Submission Plugin for Moodle

一个为Moodle作业模块设计的AI智能批改插件，支持自动分析和评分学生提交的PDF和DOCX文档。

## 功能特性

- **智能批改**: 使用AI技术自动分析学生作业并给出分数和评语
- **多格式支持**: 支持PDF和DOCX文档格式
- **灵活评分**: 支持草稿模式（教师审核）和直接发布模式
- **异步处理**: 后台处理文档解析和AI批改，不阻塞用户界面
- **错误恢复**: 自动重试失败的任务，确保处理可靠性
- **AI后端集成**: 使用local_ai_manager作为AI后端，支持多种AI服务

## 系统要求

- Moodle 4.4+
- PHP 7.4+
- local_ai_manager插件
- 文档解析API访问权限

## 安装方法

### 方法1：通过ZIP文件安装

1. 下载插件ZIP文件
2. 登录Moodle管理员账户
3. 访问 网站管理 > 插件 > 安装插件
4. 上传ZIP文件并完成安装

### 方法2：手动安装

1. 将插件文件夹复制到 `{moodle_root}/mod/assign/submission/ai_check/`
2. 访问 网站管理 > 通知 完成数据库更新
3. 或运行命令: `php admin/cli/upgrade.php`

## 配置设置

### 全局设置

访问 网站管理 > 插件 > 作业提交 > AI智能批改

- **文档解析API地址**: 文档解析服务的API地址
- **API密钥**: 访问文档解析API的密钥
- **API超时时间**: API请求的超时时间（秒）
- **最大重试次数**: 失败任务的最大重试次数
- **清理天数**: 多少天后清理旧的处理记录

### 作业设置

在创建或编辑作业时：

1. 在提交设置中找到"AI智能批改"部分
2. 勾选"启用AI自动批改"
3. 填写"参考答案/关键要点"
4. 填写"评分标准/建议"
5. 选择评分模式：
   - **保存为草稿，待教师审核**（推荐）
   - **直接发布成绩**

**注意**: 启用AI批改后，文件提交将自动限制为1个文件，仅支持PDF和DOCX格式。

## 使用流程

### 教师端

1. 创建作业时启用AI批改功能
2. 配置参考答案和评分标准
3. 选择评分模式
4. 发布作业给学生

### 学生端

1. 上传PDF或DOCX格式的作业文件
2. 提交作业
3. 系统显示"提交成功"，可以立即离开页面
4. AI在后台自动处理（草稿模式）或发布成绩（直接发布模式）

### 批改流程

1. **文档解析**: 系统调用API提取文档中的文本内容
2. **AI分析**: 根据参考答案和评分标准，AI分析学生作业
3. **生成结果**: AI给出分数和评语
4. **更新成绩**: 根据设置更新学生成绩（草稿或发布）

## API集成

### 文档解析API

插件使用外部API解析PDF和DOCX文档：

- **提交解析**: `POST /queue-task`
- **获取结果**: `GET /get-result/{task_id}`
- **认证方式**: HTTP Header `X-API-Key`

### AI后端集成

通过local_ai_manager插件调用AI服务：

```php
$manager = new \local_ai_manager\manager('feedback');
$response = $manager->perform_request($prompt, 'assignsubmission_ai_check', $context_id);
```

## 数据库结构

### assignsubmission_ai_check
存储作业的AI批改配置

### assignsubmission_ai_check_grades
存储AI批改结果和处理状态

## 定时任务

- **cleanup_old_records**: 每日凌晨2点清理旧记录
- **retry_failed_submissions**: 每30分钟重试失败的任务

## 权限设置

- `assignsubmission/ai_check:use`: 使用AI批改功能
- `assignsubmission/ai_check:configure`: 配置AI批改参数
- `assignsubmission/ai_check:viewairesults`: 查看AI批改结果

## 故障排除

### 常见问题

1. **"需要安装 local_ai_manager 插件"**
   - 确保已安装并启用local_ai_manager插件

2. **"文档解析失败"**
   - 检查API地址和密钥配置
   - 确保网络连接正常
   - 验证文件格式是否支持

3. **"AI批改失败"**
   - 检查local_ai_manager配置
   - 确保AI服务可用
   - 查看错误日志获取详细信息

### 日志查看

- Moodle日志: 网站管理 > 报告 > 日志
- 任务日志: 网站管理 > 服务器 > 任务 > 任务日志

## 开发信息

### 文件结构

```
ai_check/
├── version.php              # 版本信息
├── locallib.php            # 主要插件类
├── settings.php            # 全局设置
├── db/                     # 数据库定义
├── classes/                # PHP类文件
├── amd/src/               # JavaScript源码
├── lang/                  # 语言文件
└── README.md              # 说明文档
```

### 扩展开发

插件提供了钩子和事件系统，支持其他插件扩展功能：

- 自定义AI提示词构建
- 结果后处理
- 通知系统集成

## 许可证

本插件采用GNU GPL v3或更高版本许可证。

## 支持

如需技术支持，请：

1. 查看本文档的故障排除部分
2. 检查Moodle论坛相关讨论
3. 提交Issue到项目仓库

## 版本历史

### v1.0.0 (2025-01-01)
- 初始版本发布
- 支持PDF和DOCX文档批改
- 集成local_ai_manager
- 异步处理和错误恢复 