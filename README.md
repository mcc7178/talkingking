# hyperf-cms

<p>
<img src="https://hyperf-cms.oss-cn-guangzhou.aliyuncs.com/logo/logo_color.png" />
</p>

<p>
  <img alt="Version" src="https://img.shields.io/badge/version-1.2-blue.svg?cacheSeconds=2592000" />
  <img src="https://img.shields.io/badge/node-%3E%3D%20 12.16.1-blue.svg" />
  <img src="https://img.shields.io/badge/npm-%3E%3D%206.13.4-blue.svg" />
  <img src="https://img.shields.io/badge/php-%3E%3D7.3.0-red" />
  <a href="https://github.com/Nirongxu/vue-xuAdmin/blob/master/README.md">
    <img alt="Documentation" src="https://img.shields.io/badge/documentation-yes-brightgreen.svg" target="_blank" />
  </a>
  <a href="https://github.com/Nirongxu/vue-xuAdmin/blob/master/LICENSE">
    <img alt="License: MIT" src="https://img.shields.io/badge/License-MIT-yellow.svg" target="_blank" />
  </a>
</p>

> 基于 hyperf + vue + element 开发的 RBAC 权限管理，后台模板

## Prerequisites

- node >= 12.16.1
- npm >= 6.13.4
- php >= 7.3.0
- swoole >= 4.5.3
- hyperf >= 2.1
- vue >= 2.0
- element >= 2.15.3

## 更新日志 （显示最新版本更新日志）

# V1.3 版本更新

## 优化

1. 升级 Element 版本为 2.15.3
2. 升级 Hyperf 为最新 2.1 版本
3. 升级 webpack 打包，优化打包方式，使打包编译速度更快
4. 优化数据库表迁移文件，并增加字典，权限初始化数据操作
5. 去除一些垃圾冗余文件
6. 优化了 scss 样式问题，解决掉一些样式混乱问题
7. 优化了前端导航栏的样式，增加面包屑组件，将原有顶部导航封装成组件，通过开关控制彼此
8. 优化权限模块，增加目录菜单类型，重新生成权限初始化文件
9. 优化聊天页面样式
10. 优化登陆/注册页面的验证码，出现错误重新生成验证码
11. 优化聊天系统断线重连会反复通知用户问题，增加是否重连参数
12. 修复聊天系统中图片放大失效问题
13. 修复三级以及超过三级以上菜单路由失效问题
14. 修复群聊中用户退群后聊天记录未销毁 bug
15. 修复项目初始化操作时各种 bug 的出现
16. 修复系统日志组件路径错误问题，重新生成权限初始化列表
## 结语

如果这个框架对你有帮助的话，请不要吝啬你的 star

