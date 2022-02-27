# tongbu-spider
tongbu.eduyun.cn 采集视频课件并转码成mp4

# 安装

1. 安装ffmpeg 并设置环境变量
2. `composer install` (可选)
# 使用

```php
php src/spider.php
```

# 需要完善

1. 遍历已存在的视频并过滤，排查部分m3u8文件404采集不到的问题
2. br编码无法使用curl扩展抓取，会出现乱码，需要安装 php 的 br编码扩展
3. 代码仅作为参考学习
