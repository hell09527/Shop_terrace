from fabric.api import *

env.hosts = ["prod01.ushopal.com"]
env.user = "deploy"
env.key_filename = "~/.ssh/ids/prod01.ushopal.com/deploy/id_rsa"

def hello():
    print("hello world")
    print "env.hosts:", env.host_string
    run("uname -a")
    if (env.host_string == 'prod01.ushopal.com'):
        print("hi")


def deploy():
    'update project'
    # update
    remote_www_dir = '/var/www/data/niushop'

    print('pull code, change permission')
    with cd(remote_www_dir):
        run('make deploypreview')
        run('cp env_prod.ini .env')

    print('clean project')
    with cd(remote_www_dir):
        run('make clean')

