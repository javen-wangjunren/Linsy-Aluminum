# 产品页优化自动化方案 — WooCommerce 导出 + Skill 适配 + 处理流程

## 1. 现状梳理

### WooCommerce 实际字段 (仅 8 个)
| 字段 | 说明 | Skill 对应 |
|------|------|------------|
| `product_id` | WP post ID | 不直接使用, 但可做文件名标识 |
| `product_title` | 产品标题 = Hero H1 | `CURRENT_H1` + `CURRENT_HERO` (两个重合) |
| `product_url` | 页面 URL | `PAGE_URL` |
| `product_slug` | URL slug | 辅助标识 + 博客反向查找 |
| `product_content` | 全部页面内容 (HTML) | `CURRENT_PAGE_CONTENT` + `RAW_PAGE_CONTENT` + 内含所有表格/规格/形态 |
| `product_short_description` | Hero 短描述 | `CURRENT_HERO` 的短文案部分 |
| `seo_title` | SEO Title | `CURRENT_SEO_TITLE` |
| `seo_meta_description` | Meta Description | `CURRENT_META_DESCRIPTION` |

### Skill 期望 vs WooCommerce 现实

**关键矛盾**: planner Skill 期望的 `AVAILABLE_FORMS`, `AVAILABLE_TEMPERS`, `CURRENT_TABLES`, `PROCESSING_SCOPE`, `CERTIFICATES_AND_DOCS` 在 WooCommerce 里不存在独立字段——它们全部嵌在 `product_content` 的 HTML 中。

**结论**: 不需要导出端拆分这些字段。AI 可以从 HTML 中直接解析表格、形态列表、temper 信息。导出保持 8 字段原样, Skill 侧做适配。

---

## 2. 导出 JSON 格式定义

### 单产品导出 (`7075_aluminum.json`)

```json
{
  "export_version": "1.0",
  "export_date": "2026-07-01T09:30:00+08:00",
  "product_id": 12345,
  "product_title": "7075 Aluminum Alloy",
  "product_url": "https://linsyaluminum.com/7075-aluminum/",
  "product_slug": "7075-aluminum",
  "product_content": "<!-- 全部 HTML 内容, 包含表格/规格/形态/temper/FAQ等 -->",
  "product_short_description": "High-strength aluminum alloy for aerospace and structural applications...",
  "seo_title": "7075 Aluminum Sheet, Plate & Bar Supplier | Linsy Aluminum",
  "seo_meta_description": "7075 aluminum alloy products available in sheet, plate, bar, tube. T6/T651/T73 tempers. ISO 9001 certified. Low MOQ. Get quote.",
  "related_blogs": [
    {
      "url": "https://linsyaluminum.com/blog/7075-t6-aluminum-guide/",
      "title": "7075-T6 Aluminum Guide",
      "slug": "7075-t6-aluminum-guide"
    },
    {
      "url": "https://linsyaluminum.com/blog/6061-vs-7075-aluminum/",
      "title": "6061 vs 7075 Aluminum: Which Alloy for Your Project?",
      "slug": "6061-vs-7075-aluminum"
    }
  ]
}
```

### 批量导出 (`all_products.zip`)
ZIP 内含 N 个独立 JSON 文件, 每个文件命名规则: `{product_slug}.json`

### 博客数据来源

**当前做法**: 用户手动在 WordPress 中搜索关键词 (如 "7075"), 然后一键导出匹配 post 的标题和链接。

**导出方案**: 在产品导出 JSON 中增加 `related_blogs` 数组字段, 导出时由用户手动填写 (或从 WordPress 搜索结果中粘贴)。

**导出 UI 增加**: 在单产品导出面板中增加一个 "Search Related Blogs" 辅助按钮:
- 用户输入合金关键词 (自动从 product_title 提取, 如 "7075")
- 搜索 WordPress published posts, 返回匹配博客列表
- 用户勾选需要关联的博客 → 自动填入 related_blogs 数组
- 如果用户跳过此步, related_blogs 为空数组 (不影响后续处理)

```php
// WordPress 辅助搜索伪代码
function search_blogs_by_keyword($keyword) {
    return get_posts([
        'post_type' => 'post',
        'posts_per_page' => 50,
        'post_status' => 'publish',
        's' => $keyword  // WP 内置搜索
    ]);
}
```

**备选增强 (Phase 2)**: 全量导出时, 自动做批量反向查找 — 遍历所有 post, 检查 post_content 中是否包含各 product_url, 自动填充 related_blogs。

---

## 3. Skill / Expert 适配 (3 个改动)

### 改动 1: Agent MD Phase 1 — 更新输入格式

当前 Agent MD Phase 1 列了 14+ 个字段, 需改为匹配 WooCommerce JSON 格式:

**改前**:
```
Page URL:
Current SEO Title:
Current Meta Description:
Current H1:
Current Hero Short Description:
Current page body (paste):
Current tables: ...
Available Forms: ...
Available Tempers: ...
Certificates / MTC: ...
Processing / Surface Finish scope: ...
Related blog URLs (list): ...
```

**改后**:
```
提供产品导出 JSON 文件, 或填写以下字段:

产品导出 JSON (推荐):
  直接粘贴或上传 WooCommerce 导出的 JSON 文件

手动填写 (最低要求):
  product_url:
  product_content (HTML):
  product_short_description:
  seo_title:
  seo_meta_description:
  related_blogs (URL 列表):

注意: product_content 包含了所有表格/规格/形态/temper 信息,
      专家会从 HTML 中自动解析提取, 不需要单独提供。
```

### 改动 2: Planner Skill — 适配 RAW 输入解析

在 planner 的 Workflow 中增加一步: **"如果输入是 WooCommerce JSON, 先从 product_content 中提取结构化子字段"**

具体: planner 收到 JSON 后, 应自行从 HTML 中识别并提取:
- Available Forms (从表格/列表)
- Available Tempers (从表格/列表)
- Chemical Composition 表格
- Mechanical Properties 表格
- Available Products 表格
- Certificates / MTC 信息
- Processing / Surface Finish 信息
- FAQ 内容

提取后映射到 Skill 内部的 CURRENT_TABLES, AVAILABLE_FORMS 等变量, 后续流程不变。

### 改动 3: team-organize Skill — 增加 JSON 输入识别

在 Routing Rules 中增加:
- 如果用户直接提供了 WooCommerce 导出 JSON → 路由到 Planner (视为 discovery stage)
- 如果 JSON 中已包含 PLANNER_OUTPUT → 路由到 Rebuilder

---

## 4. 网页端导出功能设计

### UI: 两个按钮

| 按钮 | API 行为 | 输出 |
|------|----------|------|
| **Export Current Product** | 查询当前产品 8 字段 + 用户选择关联博客 → 1 个 JSON 文件 | `{slug}.json` |
| **Export All Products** | 遍历所有 published 产品 → ZIP | `all_products.zip` |

### 可选增强 (Phase 2)
- **Export Selected Products**: 复选框选择多个产品 → ZIP
- **Export Blog Catalog**: 单独导出所有博客元数据为 `blogs.csv` (url, title, slug, topic, target_keyword, related_product_slugs)
- **Import Optimized Content**: 接受 Markdown/HTML 文件, 自动写入 product_content

### API 端点建议

```
GET /wp-json/linsy/v1/export/product/{product_id}     → 单产品 JSON
GET /wp-json/linsy/v1/export/products                   → 全量 ZIP
GET /wp-json/linsy/v1/export/products?ids=1,2,3        → 指定产品 ZIP
GET /wp-json/linsy/v1/export/blog-catalog               → 博客目录 CSV
POST /wp-json/linsy/v1/import/product/{product_id}      → 导入优化稿
```

---

## 5. 处理流程设计

### 模式 A: 每周 7 个 (渐进式, 推荐)

```
Step 1: 在 WooCommerce 后台选择本周要优化的 7 个产品
Step 2: 点击 Export Selected Products → 下载 ZIP
Step 3: 解压到本地 input 文件夹
Step 4: 逐个把 JSON 文件丢给 aluminum-product-page-expert
Step 5: 专家跑 pipeline: organize → planner → rebuilder → audit
Step 6: 审计通过 → 人工确认 → 导回 WooCommerce
```

**优先级排序建议**:
- 第一批: 高搜索量合金 (6061, 7075, 5052, 6082, 5083, 2024, 3003)
- 后续批次: 搜索量次高的 + 新上线产品
- 最后: 低流量老产品 (维护性更新)

### 模式 B: 一次性 100+ (全量, 激进)

```
Step 1: Export All Products → 下载 ZIP
Step 2: 解压到本地 input 文件夹
Step 3: 按优先级分组, 每组 7-10 个
Step 4: 逐组处理, 同模式 A 的 Step 4-6
Step 5: 2-3 个月内完成全部产品
```

风险: 全量导出后如果策略中途调整, 后面的产品可能需要重新做。
缓解: 先做首批 7 个, 磨合流程后再决定是否加速。

### 模式 C: 自动化 (未来, 半自动)

当流程磨合顺畅后, 可以设置每周自动化:

```
- 每周一 09:00 自动触发
- 读取 input 文件夹中的新 JSON 文件
- 逐个调用 expert 处理
- 输出结果到 output 文件夹
- 通知用户审核
```

**当前不建议直接上自动化**, 因为:
1. 专家 pipeline 有 4 个阶段, 每个产品需要多轮对话 (5-10 turns)
2. Audit 阶段的 Hard Fail 需要人工决策
3. 先磨合 2-3 批手动流程, 再决定自动化粒度

---

## 6. 回写/导入方案

专家输出是 **publish-ready Markdown**, 但 WooCommerce 需要 HTML 写入 `product_content`。

**方案**: 在 WordPress 导入插件中内置 Markdown → HTML 转换 (用 Parsedown 或类似库), 或者用 WorkBuddy 的脚本做转换后手动粘贴。

也可让专家直接输出 HTML (在 Rebuilder 的输出格式中切换), 但 Markdown 更适合审核和版本管理。

---

## 7. 执行优先级

| 优先级 | 任务 | 说明 |
|--------|------|------|
| P0 | 确定导出 JSON 格式 | 8 字段 + related_blogs, 就本文定义的格式 |
| P0 | 网页端导出按钮开发 | 单产品 + 全量两个按钮, 让开发者照本文实现 |
| P1 | 更新 Agent MD Phase 1 | 匹配 WooCommerce JSON 输入格式 |
| P1 | 更新 planner Skill | 增加从 HTML 解析子字段的步骤 |
| P1 | 更新 team-organize Skill | 增加 JSON 输入识别路由规则 |
| P2 | 导入插件开发 | Markdown → HTML 转换 + 写入 product_content |
| P2 | blogs.csv 独立导出 | 博客目录管理 (可选, 初期手动维护也行) |
| P3 | 自动化设置 | 流程磨合后再决定 |

---

## 关键文件路径

| 文件 | 需改动 |
|------|--------|
| `~/.workbuddy/plugins/marketplaces/my-experts/plugins/aluminum-product-page-expert/agents/aluminum-product-page-expert.md` | Phase 1 输入格式 |
| `~/.workbuddy/skills/aluminum-product-page-team-organize/SKILL.md` | 增加 JSON 路由规则 |
| `~/.workbuddy/skills/aluminum-product-page-planner/SKILL.md` | 增加 HTML 解析步骤 |

---

## 立即执行: P1 Skill 适配 (3 步)

用户确认先改 Skill, 等导出功能开发完再衔接。

1. **更新 Agent MD** — Phase 1 改为接受 WooCommerce JSON 格式 (8 字段 + related_blogs), 删除 CURRENT_TABLES / AVAILABLE_FORMS 等独立字段要求, 说明 product_content 内含所有结构化数据
2. **更新 planner SKILL.md** — Workflow Step 1 增加 "如果输入是 WooCommerce JSON, 从 product_content HTML 中提取子字段" 的解析步骤
3. **更新 team-organize SKILL.md** — Routing Rules 增加 WooCommerce JSON 输入识别: JSON → Planner, JSON + planner_output → Rebuilder
