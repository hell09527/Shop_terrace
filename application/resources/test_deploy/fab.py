from fabric.api import *

env.hosts = ["dev01.ushopal.com"]
env.user = "deploy"
env.key_filename = "~/.ssh/ids/dev01.ushopal.com/deploy/id_rsa"

def hello():
    print("hello world")
    print "env.hosts:", env.host_string
    run("uname -a")
    if (env.host_string == 'dev01.ushopal.com'):
        print("hi")


def deploy():
    'update project'
    # update
    remote_www_dir = '/var/www/niushop'

    print('pull code, change permission')
    with cd(remote_www_dir):
        run('make deploypreview')
        run('cp env_dev.ini .env')

    print('clean project')
    with cd(remote_www_dir):
        run('make clean')

