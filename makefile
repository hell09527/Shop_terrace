PROJECT_NAME = NIUSHOP


# 更新代码
deploypreview:
	git pull

# 更新正式服务器代码
pro_deploy:
	fab -f application/resources/pro_deploy/fab.py  deploy

# 更新测试服务器代码
test_deploy:
	fab -f application/resources/test_deploy/fab.py  deploy

# 清除缓存
clean:
	sudo rm -rf runtime/*

# 测试
test:
	fab -f application/resources/pro_deploy/fab.py  hello

# 安装时
fix-perms:
	mkdir -p upload runtime application
	sudo chgrp -R _www upload runtime application
	sudo chmod -R g+w upload runtime application

