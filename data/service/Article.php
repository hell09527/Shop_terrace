<?php
/**
 * Article.php
 *
 * Niushop商城系统 - 团队十年电商经验汇集巨献!
 * =========================================================
 * Copy right 2015-2025 山西牛酷信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.niushop.com.cn
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================
 * @author : niuteam
 * @date : 2015.1.17
 * @version : v1.0.0.0
 */

namespace data\service;

use data\model\BcStoreActivityMasterModel;
use data\model\BcStoreActivitySlaverModel;
use data\model\BcStoreAppointmentRecordModel;
use data\model\NcCmsMasterTopicModel;
use data\model\NcCmsSlaverTopicModel;
use data\service\BaseService as BaseService;
use data\api\IArticle;
use data\model\NcCmsArticleModel;
use data\model\NcCmsArticleClassModel;
use data\model\NcCmsArticleViewModel;
use data\model\NcCmsCommentModel;
use data\model\NcCmsCommentViewModel;
use data\model\NcCmsTopicModel;
use think\Model;
use think\Cache;

/**
 * 文章服务层
 * @author Administrator
 *
 */
class Article extends BaseService implements IArticle
{
    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::getArticleList()
     */
    public function getArticleList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $data = array($page_index, $page_size, $condition, $order);
        $data = json_encode($data);

        $cache = Cache::tag("article")->get("getArticleList" . $data);
        if (empty($cache)) {
            $articleview = new NcCmsArticleViewModel();
            $list        = $articleview->getViewList($page_index, $page_size, $condition, $order);
            Cache::tag("article")->set("getArticleList" . $data, $list);
            return $list;
        } else {
            return $cache;
        }

        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::getArticleDetail()
     */
    public function getArticleDetail($article_id)
    {
        $cache = Cache::tag("article")->get("getArticleDetail" . $article_id);
        if (empty($cache)) {
            $article = new NcCmsArticleModel();
            $data    = $article->get($article_id);
            Cache::tag("article")->set("getArticleDetail" . $article_id, $data);
            return $data;
        } else {
            return $cache;
        }

        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::getArticleClass()
     */
    public function getArticleClass($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $data  = array($page_index, $page_size, $condition, $order);
        $data  = json_encode($data);
        $cache = Cache::tag("article")->get("getArticleClass" . $data);
        if (empty($cache)) {
            $article_class = new NcCmsArticleClassModel();
            $list          = $article_class->pageQuery($page_index, $page_size, $condition, $order, '*');
            Cache::tag("article")->set("getArticleClass" . $data, $list);
            return $list;
        } else {
            return $cache;
        }

        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::getArticleClassDetail()
     */
    public function getArticleClassDetail($class_id)
    {
        $cache = Cache::tag("article")->get("getArticleClassDetail" . $class_id);
        if (empty($cache)) {
            $article_class = new NcCmsArticleClassModel();
            $list          = $article_class->get($class_id);
            Cache::tag("article")->set("getArticleClassDetail" . $class_id, $list);
            return $list;
        } else {
            return $cache;
        }

        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::addArticle()
     */
    public function addArticle($title, $class_id, $short_title, $source, $url, $author, $summary, $content, $image, $keyword, $article_id_array, $click, $sort, $commend_flag, $comment_flag, $status, $attachment_path, $tag, $comment_count, $share_count)
    {
        Cache::tag("article")->clear();
        $member    = new Member();
        $user_info = $member->getUserInfoDetail($this->uid);
        $article   = new NcCmsArticleModel();
        $data      = array(
            'title'            => $title,
            'class_id'         => $class_id,
            'short_title'      => $short_title,
            'source'           => $source,
            'url'              => $url,
            'author'           => $author,
            'summary'          => $summary,
            'content'          => $content,
            'image'            => $image,
            'keyword'          => $keyword,
            'article_id_array' => $article_id_array,
            'click'            => $click,
            'sort'             => $sort,
            'commend_flag'     => $commend_flag,
            'comment_flag'     => $comment_flag,
            'status'           => $status,
            'attachment_path'  => $attachment_path,
            'tag'              => $tag,
            'comment_count'    => $comment_count,
            'share_count'      => $share_count,
            'publisher_name'   => $user_info["user_name"],
            'uid'              => $this->uid,
            'public_time'      => time(),
            'create_time'      => time()
        );
        $article->save($data);
        $data['article_id'] = $article->article_id;
        hook("articleSaveSuccess", $data);
        $retval = $article->article_id;
        return $retval;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::updateArticle()
     */
    public function updateArticle($article_id, $title, $class_id, $short_title, $source, $url, $author, $summary, $content, $image, $keyword, $article_id_array, $click, $sort, $commend_flag, $comment_flag, $status, $attachment_path, $tag, $comment_count, $share_count)
    {
        Cache::tag("article")->clear();
        $member             = new Member();
        $user_info          = $member->getUserInfoDetail($this->uid);
        $article            = new NcCmsArticleModel();
        $data               = array(
            'title'            => $title,
            'class_id'         => $class_id,
            'short_title'      => $short_title,
            'source'           => $source,
            'url'              => $url,
            'author'           => $author,
            'summary'          => $summary,
            'content'          => $content,
            'image'            => $image,
            'keyword'          => $keyword,
            'article_id_array' => $article_id_array,
            'click'            => $click,
            'sort'             => $sort,
            'commend_flag'     => $commend_flag,
            'comment_flag'     => $comment_flag,
            'status'           => $status,
            'attachment_path'  => $attachment_path,
            'tag'              => $tag,
            'comment_count'    => $comment_count,
            'share_count'      => $share_count,
            'publisher_name'   => $user_info["user_name"],
            'uid'              => $this->uid,
            'modify_time'      => time()
        );
        $retval             = $article->save($data, ['article_id' => $article_id]);
        $data['article_id'] = $article_id;
        hook("articleSaveSuccess", $data);
        return $retval;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::addAritcleClass()
     */
    public function addAritcleClass($name, $sort, $pid)
    {
        Cache::tag("article")->clear();
        $article_class = new NcCmsArticleClassModel();
        $data          = array(
            'name' => $name,
            'pid'  => $pid,
            'sort' => $sort
        );
        $retval        = $article_class->save($data);
        return $retval;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::updateArticleClass()
     */
    public function updateArticleClass($class_id, $name, $sort, $pid)
    {
        Cache::tag("article")->clear();
        $article_class = new NcCmsArticleClassModel();
        $data          = array(
            'name' => $name,
            'pid'  => $pid,
            'sort' => $sort
        );
        $retval        = $article_class->save($data, ['class_id' => $class_id]);
        return $retval;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::modifyArticleSort()
     */
    public function modifyArticleSort($article_id, $sort)
    {
        Cache::tag("article")->clear();
        $article = new NcCmsArticleModel();
        $data    = array(
            'sort' => $sort
        );
        $retval  = $article->save($data, ['article_id' => $article_id]);
        return $retval;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::modifyArticleClassSort()
     */
    public function modifyArticleClassSort($class_id, $sort)
    {
        Cache::tag("article")->clear();
        $article_class = new NcCmsArticleClassModel();
        $data          = array(
            'sort' => $sort
        );
        $retval        = $article_class->save($data, ['class_id' => $class_id]);
        return $retval;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
      * @see \data\api\cms\IArticle::deleteArticleClass()
      */
    public function deleteArticleClass($class_id)
    {
        Cache::tag("article")->clear();
        $article_class = new NcCmsArticleClassModel();
        $retval        = $article_class->destroy($class_id);
        return $retval;
    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::deleteArticle()
     */
    public function deleteArticle($article_id)
    {
        Cache::tag("article")->clear();
        $article = new NcCmsArticleModel();
        $retval  = $article->destroy($article_id);
        return $retval;
    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::articleClassUseCount()
     */
    public function articleClassUseCount($class_id)
    {
        $article        = new NcCmsArticleModel();
        $is_class_count = $article->viewCount($article, ['class_id' => $class_id]);
        return $is_class_count;
    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::getCommentList()
     */
    public function getCommentList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $commentview = new NcCmsCommentViewModel();
        $list        = $commentview->getViewList($page_index, $page_size, $condition, $order);
        return $list;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::getCommentDetail()
     */
    public function getCommentDetail($comment_id)
    {
        $comment = new NcCmsCommentModel();
        $data    = $comment->get($comment_id);
        return $data;
        // TODO Auto-generated method stub

    }

    /* (non-PHPdoc)
     * @see \data\api\cms\IArticle::deleteComment()
     */
    public function deleteComment($comment_id)
    {
        $comment = new NcCmsCommentModel();
        $retval  = $comment->destroy($comment_id);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     * @see \data\api\IArticle::getArticleClassQuery()
     */
    public function getArticleClassQuery()
    {
        $cache = Cache::tag("article")->get("getArticleClassQuery");
        if (empty($cache)) {
            $list = array();
            $list = $this->getArticleClass(1, 0, 'pid=0', 'sort');
            foreach ($list["data"] as $k => $v) {
                $second_list                    = $this->getArticleClass(1, 0, 'pid=' . $v['class_id'], 'sort');
                $list["data"][$k]['child_list'] = $second_list['data'];
            }
            Cache::tag("article")->set("getArticleClassQuery", $list);
            return $list;
        } else {
            return $cache;
        }

    }

    /**
     * 添加专题
     * @param unknown $instance_id
     * @param unknown $title
     * @param unknown $image
     * @param unknown $content
     */
    public function addTopic($instance_id, $title, $image, $content, $status)
    {
        $topic  = new NcCmsMasterTopicModel();
        $data   = array(
            'instance_id' => $instance_id,
            'title'       => $title,
            'image'       => $image,
            'content'     => $content,
            'status'      => $status,
            'create_time' => time()
        );
        $retval = $topic->save($data);
        return $retval;
    }


    /**
     * @param \data\api\unknown $instance_id
     * @param \data\api\unknown $data
     * @return bool
     * @author dai
     */
    public function addMasterTopic($instance_id, $data)
    {
        $topic = new NcCmsMasterTopicModel();
        $data  = array(
            'instance_id'  => $instance_id,
            'title'        => $data['title'],
            'sort'         => $data['sort'],
            'pic'          => $data['pic'],
            'icon_link'    => $data['icon_link'],
            'is_show'      => $data['is_show'],
            'status'       => $data['status'],
            'created_time' => time()
        );
        $res   = $topic->save($data);
        return $res;
    }

    /**
     * @param \data\api\unknown $instance_id
     * @param \data\api\unknown $data
     * @return bool
     * @author dai
     */
    public function addSlaverTopic($instance_id, $data)
    {
        $topic = new NcCmsSlaverTopicModel();
        $data  = array(
            'instance_id'  => $instance_id,
            'master_id'    => $data['master_id'],
            'title'        => $data['title'],
            'sort'         => $data['sort'],
            'title_slaver' => $data['title_slaver'],
            'title_link'   => $data['title_link'],
            'title_type'   => $data['title_type'],
            'content'      => $data['content'],
            'content_link' => $data['content_link'],
            'content_type' => $data['content_type'],
            'pic_link'     => $data['pic_link'],
            'pic'          => $data['pic'],
            'pic_pro'      => $data['pic_pro'],
            'title_pro'    => $data['title_pro'],
            'content_pro'  => $data['content_pro'],
            'movie_link'   => $data['movie_link'],
            'movie_show'   => $data['movie_show'],
            'pid'          => $data['pid'],
            'movie_link'   => $data['movie_link'],
            'movie_show'   => $data['movie_show'],
            'created_time' => time()
        );
        $res   = $topic->save($data);
        return $res;
    }


    /**
     * 专题列表
     * (non-PHPdoc)
     * @see \data\api\IArticle::getTopicList()
     */
    public function getTopicList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $topic = new NcCmsTopicModel();
        $list  = $topic->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
        // TODO Auto-generated method stub

    }

    /**
     * 获取详情
     * (non-PHPdoc)
     * @see \data\api\IArticle::getTopicDetail()
     */
    public function getTopicDetail($topic_id)
    {
        $topic = new NcCmsTopicModel();
        $list  = $topic->get($topic_id);
        return $list;
    }

    /**
     * 获取详情
     * (non-PHPdoc)
     * @see \data\api\IArticle::getTopicDetail()
     */
    public function getMasterTopicDetail($topic_id)
    {
        $topic = new NcCmsMasterTopicModel();
        $list  = $topic->get($topic_id);
        return $list;
    }


    /**
     * 获取详情
     * (non-PHPdoc)
     * @see \data\api\IArticle::getTopicDetail()
     */
    public function getSlaverTopicDetail($topic_id)
    {
        $topic            = new NcCmsSlaverTopicModel();
        $res['master_id'] = $topic_id;
        $list             = $topic->all($res);
        return $list;
    }

    /**
     * 修改专题
     * @param unknown $instance_id
     * @param unknown $topic_id
     * @param unknown $title
     * @param unknown $image
     * @param unknown $content
     * @param unknown $status
     */
    public function updateTopic($instance_id, $topic_id, $title, $image, $content, $status)
    {
        $topic  = new NcCmsTopicModel();
        $data   = array(
            'instance_id' => $instance_id,
            'title'       => $title,
            'image'       => $image,
            'content'     => $content,
            'status'      => $status,
            'modify_time' => time()
        );
        $retval = $topic->save($data, ['topic_id' => $topic_id]);
        return $retval;
    }


    /**
     * @param $instance_id
     * @param $data
     * @return bool
     */
    public function updateMasterTopic($instance_id, $data)
    {
        $topic  = new NcCmsMasterTopicModel();
        $data   = array(
            'instance_id' => $instance_id,
            'id'          => $data['id'],
            'title'       => $data['title'],
            'pic'         => $data['pic'],
            'sort'        => $data['sort'],
            'icon_link'   => $data['icon_link'],
            'is_show'     => $data['is_show'],
            'status'      => $data['status'],
            'modify_time' => time()
        );
        $retval = $topic->save($data, ['id' => $data['id']]);
        return $retval;
    }

    /**
     * @param $instance_id
     * @param $data
     * @return bool
     */
    public function updateSlaverTopic($instance_id, $data)
    {
        $topic  = new NcCmsSlaverTopicModel();
        $data   = array(
            'instance_id'  => $instance_id,
            'id'           => $data['id'],
            'master_id'    => $data['master_id'],
            'title'        => $data['title'],
            'sort'         => $data['sort'],
            'title_slaver' => $data['title_slaver'],
            'title_link'   => $data['title_link'],
            'title_type'   => $data['title_type'],
            'content'      => $data['content'],
            'content_link' => $data['content_link'],
            'content_type' => $data['content_type'],
            'pic_link'     => $data['pic_link'],
            'pic_pro'      => $data['pic_pro'],
            'pic'          => $data['pic'],
            'title_pro'    => $data['title_pro'],
            'content_pro'  => $data['content_pro'],
            'pid'          => $data['pid'],
            'movie_link'   => $data['movie_link'],
            'movie_show'   => $data['movie_show'],
            'modify_time'  => time()
        );
        $retval = $topic->save($data, ['id' => $data['id']]);
        return $retval;
    }

    /**
     * 删除专题
     * @param unknown $instance_id
     * @param unknown $topic_id
     */
    public function deleteTopic($topic_id)
    {
        $topic  = new NcCmsMasterTopicModel();
        $retval = $topic->destroy($topic_id);
        return $retval;
    }

    /**
     * 文章分类修改单个字符
     * (non-PHPdoc)
     * @see \data\api\IArticle::cmsfyField()
     */
    public function cmsField($class_id, $sort, $name)
    {
        Cache::tag("article")->clear();
        $article_class = new NcCmsArticleClassModel();
        $data          = array(
            $sort => $name,
        );
        $retval        = $article_class->save($data, ['class_id' => $class_id]);
        return $retval;
        // TODO Auto-generated method stub
    }

    /**
     * 专题列表
     * (non-PHPdoc)
     * @see \data\api\IArticle::getTopicList()
     */
    public function getAdminTopicList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $topic = new NcCmsMasterTopicModel();
        $list  = $topic->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
        // TODO Auto-generated method stub

    }


    /**
     * @return false|static[]
     * 获取活动列表
     */
    public function getMasterTopic()
    {
        $master_topic = new NcCmsMasterTopicModel();
        $list         = $master_topic->getQuery([
            'is_show' => 1,
            'status'  => 1
        ], '*', "sort asc");
        return $list;
        // TODO Auto-generated method stub
    }

    /**
     * @param $master_id
     * @return mix
     * 获取活动详情
     */
    public function getSlaverTopic($master_id)
    {
        $slaver_topic = new NcCmsSlaverTopicModel();
        $list         = $slaver_topic->getQuery([
            'master_id' => $master_id
        ], '*', "sort ASC");
        return $list;
    }

    /**
     * @param $master_id
     * @return mix
     * 获取活动详情id
     */
    public function getSlaverTopicIds($master_id)
    {
        $slaver_topic = new NcCmsSlaverTopicModel();
        $list         = $slaver_topic->getQuery([
            'master_id' => $master_id
        ], 'id', "created_time asc");
        return $list;
    }

    /**
     * @param $id
     * @return int
     */
    public function deleteSlaverTopic($id)
    {
        $topic  = new NcCmsSlaverTopicModel();
        $retval = $topic->destroy(['id' => $id]);
        return $retval;
    }

    /**
     * @param \data\api\unknown $data
     * @return bool
     * @author dai
     */
    public function addStoreMasterTopic($data)
    {
        $store_master = new BcStoreActivityMasterModel();
        $data  = array(
            'store_id'      => $data['store_id'],
            'activity_name' => $data['activity_name'],
            'start_time'    => getTimeTurnTimeStamp($data['start_time']),
            'end_time'      => getTimeTurnTimeStamp($data['end_time']),
            'province_id'   => $data['province_id'],
            'city_id'   => $data['city_id'],
            'district_id'   => $data['district_id'],
            'address'   => $data['address'],
            'cover_pic'     => $data['cover_pic'],
            'extension_pic' => $data['extension_pic'],
            'is_show'       => $data['is_show'],
            'status'        => $data['status'],
            'create_time'   => time()
        );
        $res   = $store_master->save($data);
        return $res;
    }

    /**
     * @param \data\api\unknown $data
     * @return bool
     * @author dai
     */
    public function addStoreSlaverTopic($data)
    {
        $store_slaver = new BcStoreActivitySlaverModel();

        $data = array(
            'master_id'       => $data['master_id'],
            'index_pic'       => $data['index_pic'],
            'pic_link'        => $data['pic_link'],
            'index_video'     => $data['index_video'],
            'video_pic'       => $data['video_pic'],
            'start_time'      => getTimeTurnTimeStamp($data['start_time']),
            'end_time'        => getTimeTurnTimeStamp($data['end_time']),
            'appointment_pic' => $data['appointment_pic'],
            'invite_pic'      => $data['invite_pic'],
            'coupon_pic'      => $data['coupon_pic']
        );
        $res   = $store_slaver->save($data);
        return $res;
    }

    /**
     * 门店活动列表
     * (non-PHPdoc)
     */
    public function getStoreActivityList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $master_store = new BcStoreActivityMasterModel();
        $list  = $master_store->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $list;
    }


    /**
     * 删除活动
     * @param unknown $id
     */
    public function deleteStoreActivity($id)
    {
        $topic  = new BcStoreActivityMasterModel();
        $retval = $topic->destroy($id);
        return $retval;
    }


    /**
     * @param $instance_id
     * @param $data
     * @return bool
     */
    public function updateMasterActivity($data)
    {
        $topic  = new NcCmsMasterTopicModel();
        $data   = array(
            'id'          => $data['id'],
            'title'       => $data['title'],
            'pic'         => $data['pic'],
            'sort'        => $data['sort'],
            'icon_link'   => $data['icon_link'],
            'is_show'     => $data['is_show'],
            'status'      => $data['status'],
            'modify_time' => time()
        );
        $retval = $topic->save($data, ['id' => $data['id']]);
        return $retval;
    }

    /**
     * @param $instance_id
     * @param $data
     * @return bool
     */
    public function updateSlaverActivity($data)
    {
        $topic  = new NcCmsSlaverTopicModel();
        $data   = array(
            'id'           => $data['id'],
            'master_id'    => $data['master_id'],
            'title'        => $data['title'],
            'sort'         => $data['sort'],
            'title_slaver' => $data['title_slaver'],
            'title_link'   => $data['title_link'],
            'title_type'   => $data['title_type'],
            'content'      => $data['content'],
            'content_link' => $data['content_link'],
            'content_type' => $data['content_type'],
            'pic_link'     => $data['pic_link'],
            'pic_pro'      => $data['pic_pro'],
            'pic'          => $data['pic'],
            'title_pro'    => $data['title_pro'],
            'content_pro'  => $data['content_pro'],
            'pid'          => $data['pid'],
            'movie_link'   => $data['movie_link'],
            'movie_show'   => $data['movie_show'],
            'modify_time'  => time()
        );
        $retval = $topic->save($data, ['id' => $data['id']]);
        return $retval;
    }


    /**
     * @param $master_id
     * @return mix
     * 获取活动详情id
     */
    public function getSlaverActivityIds($master_id)
    {
        $slaver_activity = new BcStoreActivitySlaverModel();
        $list         = $slaver_activity->getQuery([
            'master_id' => $master_id
        ], 'id', "create_time asc");
        return $list;
    }


    /**
     * @param $id
     * @return BcStoreActivityMasterModel
     * @throws \think\Exception\DbException
     * 查看活动详情
     */
    public function getMasterActivityDetail($id)
    {
        $master_store = new BcStoreActivityMasterModel();
        $list         = $master_store->get($id);
        return $list;
    }

    /**
     * @param $master_id
     * @return BcStoreActivitySlaverModel[]|false
     * @throws \think\Exception\DbException
     * 查看活动详情
     */
    public function getSlaverActivityDetail($master_id)
    {
        $slaver_activity  = new BcStoreActivitySlaverModel();
        $res['master_id'] = $master_id;
        $list             = $slaver_activity->all($res);
        return $list;
    }


    /**
     * @param $appointment_id
     * @param $uid
     * @return bool
     * 添加预约记录
     */
//    public function storeActivityAppointment($appointment_id,$uid){
//
//        $store_appointment = new BcStoreAppointmentRecordModel();
//        $store_slaver      = new BcStoreActivitySlaverModel();
//        $store_slaver->startTrans();
//        try {
//            $store_slaver_info      = $store_slaver->getInfo(['id' => $appointment_id]);
//            $store_appointment_info = $store_appointment->getInfo(['appointment_id' => $appointment_id,'uid' => $uid]);
//
//            if( $store_appointment_info ){
//                return '-2';//预约成功
//            }
//
//
//            if( $store_slaver_info['user_num'] < 1 ) {
//                return '-3';//已预约 勿重复预约
//            }
//
//            $new_num = $store_slaver_info['user_num'] - 1;
//            $store_slaver->save(['user_num' => $new_num],['id' => $appointment_id]);
//
//            $data = [
//                'appointment_id'   => $appointment_id,
//                'uid'              => $uid,
//                'appointment_time' => time()
//            ];
//
//            $res = $store_appointment->save($data);
//            $store_slaver->commit();
//            return $res;
//
//        } catch (\Exception $e) {
//
//            $store_slaver->rollback();
//
//            return $e->getMessage();
//
//        }
//
//    }

    # 门店活动列表
    public function storeActivityList()
    {
        $master_store = new BcStoreActivityMasterModel();
        $list         = $master_store->getQuery([
            'is_show' => 1
        ], '*', "create_time desc");
        return $list;
    }

    # 门店活动详情
    public function storeActivityDetail($master_id,$uid)
    {
        $master_model = new BcStoreActivityMasterModel();
        $activity = $master_model->getInfo(['id' => $master_id]);

        $address = new Address();
        $activity['province_name'] = $address->getProvinceName($activity['province_id']);
        $activity['city_name'] = $address->getCityName($activity['city_id']);
        $activity['dictrict_name'] = $address->getDistrictName($activity['district_id']);

        $slaver_model = new BcStoreActivitySlaverModel();
        $activity['slaver'] = $slaver_model->getQuery(['master_id' => $master_id],'*','id asc');

        //预约信息
        $appointment_model = new BcStoreAppointmentRecordModel();
        $activity['appointment'] = $appointment_model->getInfo(['master_id' => $master_id,'uid' => $uid]);
        return $activity;
    }

    # 门店活动预约
    public function storeActivityAppointment($master_id,$uid,$name,$tel,$remarks){

        $store_appointment = new BcStoreAppointmentRecordModel();
        $store_appointment_info = $store_appointment->getInfo(['master_id' => $master_id,'uid' => $uid]);

        if(!empty($store_appointment_info)){
            return '-1'; //已预约 请勿重复预约
        }

        $data = [
            'master_id' => $master_id,
            'uid' => $uid,
            'name' => $name,
            'tel' => $tel,
            'remarks' => $remarks,
            'appointment_time' => time()
        ];

        $res = $store_appointment->save($data);
        return $res;
    }
}
