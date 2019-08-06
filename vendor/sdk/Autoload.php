<?php
    class Autoload{
        // 自动加载类
        public function load_class($className)
        {
            $classMap = $this->class_map();

            if (isset($classMap[$className])) {
                // 包含内核文件
                $file = $classMap[$className];
            } elseif (strpos($className, '\\') !== false) {
                // 包含应用（application目录）文件
                $file = OMS_SDK_PATH . str_replace('\\', '/', $className) . '.php';
                if (!is_file($file)) {
//                    require APP_PATH.'/vendor/autoload.php';
                    return;
                }
            } else {
                return;
            }

            include $file;
        }

        # 特殊文件命名空间映射关系
        protected function class_map()
        {
            return [

            ];
        }

        // 运行程序
        public function run()
        {
            spl_autoload_register(array($this, 'load_class'));
        }
    }