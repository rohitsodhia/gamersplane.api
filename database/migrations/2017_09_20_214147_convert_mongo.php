<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\Models\Forum;
use App\Models\ForumGroup;
use App\Models\ForumGroupMembership;
use App\Models\ForumPermission;
use App\Models\Thread;
use App\Models\Post;
use App\Models\LastPost;

class ConvertMongo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::table('users', function (Blueprint $table) {
    		$table->softDeletes();
			$table->string('password', 255)->change();
		});

		$games = DB::connection('mongo')->collection('games')->get();
		foreach ($games as $game) {
			foreach ($game['players'] as &$player) {
				$player = [
					'approved' => $player['approved'],
					'isGM' => $player['isGM'],
					'user' => [
						'userId' => $player['user']['userID'],
						'username' => $player['user']['username']
					]
				];
			}
			DB::connection('mongo')->collection('games')->where('_id', $game['_id'])->update($game);
		}
		DB::connection('mongo')->collection('games')->update([
			'$rename' => [
				'gameID' => 'gameId',
				'forumID' => 'forumId',
				'groupID' => 'groupId',
				'gm.userID' => 'gm.userId'
			]
		]);

/*		$maxIds = [];

		Schema::connection('mongo')->drop('forums_groups');
		Schema::connection('mongo')->create('forums_groups', function ($collection) {
			$collection->unique('groupId');
		});
		$maxIds['groupId'] = 0;
		$groups = DB::connection('mysql')->table('forums_groups')->get();
		foreach ($groups as $group) {
			if ($group->groupID > $maxIds['groupId']) {
				$maxIds['groupId'] = $group->groupID;
			}
			ForumGroup::create([
				'groupId' => $group->groupID,
				'name' => $group->name,
				'status' => $group->status,
				'ownerId' => $group->ownerID,
				'gameId' => $group->gameID
			]);
		}

		Schema::connection('mongo')->drop('forums_groupMemberships');
		Schema::connection('mongo')->create('forums_groupMemberships', function ($collection) {
			$collection->unique(['groupId', 'userId']);
		});
		$memberships = DB::connection('mysql')->table('forums_groupMemberships')->get();
		foreach ($memberships as $membership) {
			ForumGroupMembership::create([
				'groupId' => $membership->groupID,
				'userId' => $membership->userID
			]);
		}

		Schema::connection('mongo')->drop('forums_permissions');
		Schema::connection('mongo')->create('forums_permissions', function ($collection) {
			$collection->index('forumId', 'type', 'typeId');
		});
		$permissions = DB::connection('mysql')->table('forums_permissions_general')->get();
		foreach ($permissions as $permission) {
			ForumPermission::create([
				'forumId' => $permission->forumID,
				'type' => 'general',
				'read' => $permission->read,
				'write' => $permission->write,
				'editPost' => $permission->editPost,
				'deletePost' => $permission->deletePost,
				'createThread' => $permission->createThread,
				'deleteThread' => $permission->deleteThread,
				'addPoll' => $permission->addPoll,
				'addRolls' => $permission->addRolls,
				'addDraws' => $permission->addDraws,
				'moderate' => $permission->moderate,
			]);
		}
		$permissions = DB::connection('mysql')->table('forums_permissions_groups')->get();
		foreach ($permissions as $permission) {
			ForumPermission::create([
				'forumId' => $permission->forumID,
				'type' => 'group',
				'typeId' => $permission->groupID,
				'read' => $permission->read,
				'write' => $permission->write,
				'editPost' => $permission->editPost,
				'deletePost' => $permission->deletePost,
				'createThread' => $permission->createThread,
				'deleteThread' => $permission->deleteThread,
				'addPoll' => $permission->addPoll,
				'addRolls' => $permission->addRolls,
				'addDraws' => $permission->addDraws,
				'moderate' => $permission->moderate,
			]);
		}
		$permissions = DB::connection('mysql')->table('forums_permissions_users')->get();
		foreach ($permissions as $permission) {
			$permission = ForumPermission::create([
				'forumId' => $permission->forumID,
				'type' => 'user',
				'typeId' => $permission->userID,
				'read' => $permission->read,
				'write' => $permission->write,
				'editPost' => $permission->editPost,
				'deletePost' => $permission->deletePost,
				'createThread' => $permission->createThread,
				'deleteThread' => $permission->deleteThread,
				'addPoll' => $permission->addPoll,
				'addRolls' => $permission->addRolls,
				'addDraws' => $permission->addDraws,
				'moderate' => $permission->moderate,
			]);
		}

		$postCounts = [];
		$maxIds['postId'] = 0;
		Schema::connection('mongo')->drop('posts');
		Schema::connection('mongo')->create('posts', function ($collection) {
			$collection->unique('postId');
			$collection->index('threadId', 'datePosted');
		});
		$posts = DB::connection('mysql')->select("SELECT p.postID, p.threadID, p.title, p.authorID, a.username, p.message, p.datePosted, p.lastEdit, p.timesEdited, p.postAs FROM posts p INNER JOIN users a ON p.authorID = a.userID ORDER BY p.postID");
		foreach ($posts as $post) {
			if ($post->postID > $maxIds['postId']) {
				$maxIds['postId'] = $post->postID;
			}
			Post::create([
				'postId' => $post->postID,
				'threadId' => $post->threadID,
				'title' => $post->title,
				'author' => [
					'userId' => $post->authorID,
					'username' => $post->username,
				],
				'message' => $post->message,
				'datePosted' => $post->datePosted,
				'lastEdit' => $post->lastEdit != '0000-00-00 00:00:00' ? $post->lastEdit : null,
				'timesEdited' => $post->timesEdited,
				'postAs' => $post->postAs
			]);
			if (!isset($postCounts[$post->threadID])) {
				$postCounts[$post->threadID] = 0;
			}
			$postCounts[$post->threadID]++;
		}

		$forumLPs = [];
		$threadCounts = [];
		$totalPostCounts = [];
		$maxIds['threadId'] = 0;
		Schema::connection('mongo')->drop('threads');
		Schema::connection('mongo')->create('threads', function ($collection) {
			$collection->unique('threadId');
			$collection->index('forumId', 'datePosted');
		});
		$threads = DB::connection('mysql')->select('SELECT t.threadID, t.forumID, fp.title, fp.authorID, fpa.username, fp.datePosted, lp.postID, lp.title lp_title, lp.authorID lp_authorID, lpa.username lp_username, lp.datePosted lp_datePosted, t.sticky, t.locked, t.allowRolls, t.allowDraws, t.postCount FROM threads t INNER JOIN posts fp ON t.firstPostID = fp.postID INNER JOIN users fpa ON fp.authorID = fpa.userID INNER JOIN posts lp ON t.lastPostID = lp.postID INNER JOIN users lpa ON fp.authorID = lpa.userID ORDER BY t.threadID');
		foreach ($threads as $thread) {
			if ($thread->threadID > $maxIds['threadId']) {
				$maxIds['threadId'] = $thread->threadID;
			}
			$newThread = Thread::create([
				'threadId' => $thread->threadID,
				'forumId' => $thread->forumID,
				'title' => $thread->title,
				'author' => [
					'userId' => $thread->authorID,
					'username' => $thread->username,
				],
				'datePosted' => new MongoDB\BSON\UTCDateTime(strtotime($thread->datePosted) * 1000),
				'sticky' => (bool) $thread->sticky,
				'locked' => (bool) $thread->locked,
				'allowRolls' => (bool) $thread->allowRolls,
				'allowDraws' => (bool) $thread->allowDraws,
				'postCount' => $postCounts[$thread->threadID]
			]);
			$lastPost = new LastPost([
				'postId' => $thread->postID,
				'title' => $thread->lp_title,
				'author' => [
					'userId' => $thread->lp_authorID,
					'username' => $thread->lp_username
				],
				'datePosted' => $thread->lp_datePosted
			]);
			$newThread->lastPost()->save($lastPost);
			if (!isset($forumLPs[$thread->forumID]) || $forumLPs[$thread->forumID]->datePosted < $lastPost->datePosted) {
				$forumLPs[$thread->forumID] = $lastPost;
			}

			if (!isset($threadCounts[$thread->forumID])) {
				$threadCounts[$thread->forumID] = 0;
			}
			$threadCounts[$thread->forumID]++;
			if (!isset($totalPostCounts[$thread->forumID])) {
				$totalPostCounts[$thread->forumID] = 0;
			}
			$totalPostCounts[$thread->forumID] += $postCounts[$thread->threadID];
		}

		Schema::connection('mongo')->drop('forums');
		Schema::connection('mongo')->create('forums', function ($collection) {
			$collection->unique('forumId');
			$collection->index('parentId');
			$collection->index('heritage');
		});

		$rForumAdmins = DB::connection('mysql')->table('forumAdmins')->get();
		$forumAdmins = [];
		foreach ($rForumAdmins as $forumAdmin) {
			if (!isset($forumAdmins[$forumAdmin->forumID])) {
				$forumAdmins[$forumAdmin->forumID] = [];
			}
			$forumAdmins[$forumAdmin->forumID][] = $forumAdmin->userID;
		}

		$children = [];
		$maxIds['forumId'] = 0;
        $forums = DB::connection('mysql')->table('forums')->orderBy('forumID')->get();
		foreach ($forums as $forum) {
			if ($forum->forumID > $maxIds['forumId']) {
				$maxIds['forumId'] = $forum->forumID;
			}
			$forum->heritage = array_merge([0], explode('-', $forum->heritage));
			if ($forum->forumID === 0) {
				$forum->heritage = [0];
			}
			foreach ($forum->heritage as &$val) {
				$val = (int) $val;
			}
			// array_pop($forum->heritage);
			// if ($forum->forumID === 0) {
			// 	$forum->heritage = [];
			// }
			if (!isset($children[$forum->parentID])) {
				$children[$forum->parentID] = [];
			}
			$children[$forum->parentID][$forum->order] = $forum->forumID;
			$newForum = Forum::create([
				'forumId' => $forum->forumID,
				'title' => $forum->title,
				'description' => $forum->description,
				'type' => $forum->forumType,
				'parentId' => $forum->parentID,
				'heritage' => $forum->heritage,
				'depth' => sizeof($forum->heritage),
				'order' => $forum->order,
				'children' => [],
				'gameId' => $forum->gameID,
				'threadCount' => isset($threadCounts[$forum->forumID]) ? $threadCounts[$forum->forumID] : 0,
				'postCount' => isset($totalPostCounts[$forum->forumID]) ? $totalPostCounts[$forum->forumID] : 0,
				'admins' => isset($forumAdmins[$forum->forumID]) ? $forumAdmins[$forum->forumID] : [null]
			]);
			if (isset($forumLPs[$forum->forumID])) {
				$lastPost = new LastPost($forumLPs[$forum->forumID]->toArray());
			} else {
				$lastPost = new LastPost([
					'postId' => null,
					'title' => null,
					'author' => [
						'userId' => null,
						'username' => null
					],
					'datePosted' => null
				]);
			}
			$newForum->lastPost()->save($lastPost);
		}

		foreach ($children as $forumId => $iChildren) {
			if ($forumId !== '' && sizeof($iChildren)) {
				$forum = Forum::find($forumId);
				ksort($iChildren);
				$forum->children = array_values($iChildren);
				$forum->save();
			}
		}

		Schema::connection('mongo')->drop('forums_readData_forums');
		Schema::connection('mongo')->create('forums_readData_forums', function ($collection) {
			$collection->index('userId', 'forumId');
		});
		$readData = DB::connection('mysql')->select('SELECT userID, forumID, markedRead FROM forums_readData_forums');
		foreach ($readData as $iReadData) {
			DB::connection('mongo')->collection('forums_readData_forums')->insert([
				'userId' => (int) $iReadData->userID,
				'forumId' => (int) $iReadData->forumID,
				'markedRead' => (int) $iReadData->markedRead,
			]);
		}
		Schema::connection('mongo')->drop('forums_readData_threads');
		Schema::connection('mongo')->create('forums_readData_threads', function ($collection) {
			$collection->index('userId', 'forumId');
			$collection->index('userId', 'threadId');
		});
		$readData = DB::connection('mysql')->select('SELECT rd.userID, rd.threadID, t.forumID, rd.lastRead FROM forums_readData_threads rd INNER JOIN threads t ON rd.threadID = t.threadID');
		foreach ($readData as $iReadData) {
			DB::connection('mongo')->collection('forums_readData_threads')->insert([
				'userId' => (int) $iReadData->userID,
				'threadId' => (int) $iReadData->threadID,
				'forumId' => (int) $iReadData->forumID,
				'lastRead' => (int) $iReadData->lastRead,
			]);
		}

		Schema::connection('mongo')->drop('forums_subscriptions');
		Schema::connection('mongo')->create('forums_subscriptions', function ($collection) {
			$collection->index('userId', 'forumId');
			$collection->index('userId', 'threadId');
		});
		$subs = DB::connection('mysql')->select('SELECT userID, type, ID FROM forumSubs');
		foreach ($subs as $sub) {
			if ($sub->type === 'f') {
				DB::connection('mongo')->collection('forums_subscriptions')->insert([
					'userId' => $sub->userID,
					'forumId' => $sub->ID
				]);
			} else {
				DB::connection('mongo')->collection('forums_subscriptions')->insert([
					'userId' => $sub->userID,
					'threadId' => $sub->ID
				]);
			}
		}

		foreach ($maxIds as $key => $value) {
			DB::connection('mongo')->collection('counters')->where('_id', $key)->update(['seq' => $value], ['upsert' => true]);
		}*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::table('users', function (Blueprint $table) {
		    $table->dropColumn('deleted_at');
			$table->string('password', 64)->change();
		});

		$games = DB::connection('mongo')->collection('games')->get();
		foreach ($games as $game) {
			foreach ($game['players'] as &$player) {
				$player = [
					'approved' => $player['approved'],
					'isGM' => $player['isGM'],
					'user' => [
						'userId' => $player['user']['userID'],
						'username' => $player['user']['username']
					]
				];
			}
			DB::connection('mongo')->collection('games')->where('_id', $game['_id'])->update($game);
		}
		DB::connection('mongo')->collection('games')->update([
			'$rename' => [
				'gameId' => 'gameID',
				'forumId' => 'forumID',
				'groupId' => 'groupID',
				'gm.userId' => 'gm.userID'
			]
		]);

/*		Schema::connection('mongo')->drop('forums');
		Schema::connection('mongo')->drop('forums_groups');
		Schema::connection('mongo')->drop('forums_groupMemberships');
		Schema::connection('mongo')->drop('forums_permissions');
		Schema::connection('mongo')->drop('threads');
		Schema::connection('mongo')->drop('posts');
		Schema::connection('mongo')->drop('forums_readData_forums');
		Schema::connection('mongo')->drop('forums_readData_threads');*/
    }
}
