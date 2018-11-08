# Yilu-Tech file-center

文件管理服务

## 安装

#### 添加源

        "repositories": [
            {
                "type": "git",
                "url": "git@share-git.yilu.works:yilu-tech/file-center.git"
            },
            {
                "type": "git",
                "url": "git@share-git.yilu.works:yilu-tech/micro-api.git"
            }
        ]

#### 安装包

        composer require yilu-tech/file-center

#### 注册provider
        // 服务端 route provider
        YiluTech\FileCenter\FileRouteServiceProvider::class
        
        // 内网客户端 client provider, 使用 facade 需注入 
        YiluTech\FileCenter\FileRouteServiceProvider::class
        
        // 如果使用 OSS 
        YiluTech\FileCenter\AliyunOss\AliyunOssServiceProvider::class

#### 服务端配置
        
        // 在 filesystems.php 添加 buckets
        
        // 例：
        "buckets" => [
            $bucket => [            // $bucket bucket名称
                "disk" => "oss",    // 磁盘驱动
                "root" => "dev"     // root目录
            } ...
        ]

#### 内网客户端配置

        // .env
        FILE_BUCKET=$bucket
        FILE_URI_PREFIX=    // 链接前缀

## 实例

#### 服务端

    public function uploadImage(Request $request)       // 文件上传
        {
            if ($request->has('cut')) { // 判断是否需要裁剪图片
                $rules = [
                    'image' => 'required|file|max:2048',
                    'src_x' => 'required|numeric',
                    'src_y' => 'required|numeric',
                    'dst_w' => 'required|numeric|min:8',
                    'dst_h' => 'required|numeric|min:8',
                    'src_w' => 'required|numeric|min:8',
                    'src_h' => 'required|numeric|min:8',
                ];
            } else {
                $rules = [
                    'images' => 'required|array|min:1',
                    'images.*' => 'file|max:2048'
                ];
            }
            $rules['bucket'] = 'required|string|max:16';        // bucket
    
            $this->validate($request, $rules);
    
            $server = new Server($request->input('bucket')); // 初始实例
    
            $is_temp = (int)$request->input('temp', 1);     // 判断是否存到暂存目录，默认开启
    
            if ($request->has('cut')) {
                return $is_temp ? $server->storeTempWithCut($request->all()) :
                    $server->storeWithCut($request->all());
            } else {
                foreach ($request->file('images') as $item) {
                    $paths[] = $is_temp ? $server->storeTemp($item) : $server->store($item);
                }
                return $paths;
            }
        }
        
#### 内网客户端
        
        try {
            \DB::beginTransaction();
            \FileCenterClient::prefix($prefix)->prepare(); 
            
            \DB::table('xx')->insert([...]);
            \FileCenterClient::move('$temp/2018-01-01/xxx.png');
            \FileCenterClient::delete('xxx.png');
            
            \FileCenterClient::commit();  //  在数据库之前 commit
            \DB::commit();
        
        } cache(\Exception $exception) {
            \DB::rollback();
            \FileCenterClient::rollback();  //  在数据库之后 rollback
        }