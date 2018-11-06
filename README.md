# Yilu-Tech file-upload

文件 上传, 管理 服务

## 安装

#### 添加源

        "repositories": [
            {
                "type": "git",
                "url": "git@share-git.yilu.works:yilu-tech/file-upload.git"
            },
            {
                "type": "git",
                "url": "git@share-git.yilu.works:yilu-tech/micro-api.git"
            }
        ]

#### 安装包

        composer require yilu-tech/file-upload

#### 注册provider
    
        YiluTech\FileUpload\FileRouteServiceProvider::class
        // 如果使用 OSS 
        YiluTech\FileUpload\AliyunOss\AliyunOssServiceProvider::class

#### 服务端配置
        
        // 在 filesystems.php 添加 buckets
        
        // 例：
        "buckets" => [
            $bucket => [            // $bucket bucket名称
                "disk" => "oss",    // 磁盘驱动
                "prefix" => "dev"   // 目录前缀
            } ...
        ]

#### 内网客户端配置

        // env.ts
        FILE_BUCKET=$bucket

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
            $rules['instance'] = 'required';                    // instance 关联账户目录
            $rules['bucket'] = 'required|string|max:16';        // bucket
    
            $this->validate($request, $rules);
    
            $server = new Server($request->input('bucket'), $request->input('instance')); // 初始实例
    
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
        
        $client = new client($instance);
        
        try {
            \DB::beginTransaction();
            $client->prepare();
            
            \DB::table('xx')->insert([...]);
            $client->move('$temp/2018-01-01/xxx.png');
            $client->delete('xxx.png');
            
            $client->commit();
            \DB::commit();
        
        } cache(\Exception $exception) {
            \DB::roleback();
            $client->roleback();
        }