<?php


namespace OCA\Bookmarks\Tests;

use OCA\Bookmarks\Db;
use OCA\Bookmarks\Exception\UrlParseError;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use PHPUnit\Framework\TestCase;


class FindTest extends TestCase {

	/**
	 * @var Db\BookmarkMapper
	 */
	private $bookmarkMapper;

	/**
	 * @var Db\TagMapper
	 */
	private $tagMapper;

	/**
	 * @var Db\FolderMapper
	 */
	private $folderMapper;

	/**
	 * @var string
	 */
	private $userId;

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws QueryException
	 * @throws UrlParseError
	 * @throws \OCA\Bookmarks\Exception\AlreadyExistsError
	 * @throws \OCA\Bookmarks\Exception\UserLimitExceededError
	 * @throws \OC\DatabaseException
	 */
	protected function setUp(): void {
		parent::setUp();

		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders');
		$query->execute();
		$query = \OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_folders_bookmarks');
		$query->execute();

		$this->bookmarkMapper = \OC::$server->query(Db\BookmarkMapper::class);
		$this->tagMapper = \OC::$server->query(Db\TagMapper::class);
		$this->folderMapper = \OC::$server->query(Db\FolderMapper::class);

		$this->userManager = \OC::$server->getUserManager();
		$this->user = 'test';
		if (!$this->userManager->userExists($this->user)) {
			$this->userManager->createUser($this->user, 'password');
		}
		$this->userId = $this->userManager->get($this->user)->getUID();

		foreach($this->singleBookmarksProvider() as $bookmarkEntry) {
			$bookmarkEntry[1]->setUserId($this->userId);
			$bookmark = $this->bookmarkMapper->insertOrUpdate($bookmarkEntry[1]);
			$this->tagMapper->addTo($bookmarkEntry[0], $bookmark->getId());
		}
	}

	public function testFindAll() {
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ["wikipedia"]);
		$this->assertSame(1, count($bookmarks));
	}


	public function testFindAllWithAnd() {
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['wikipedia', 'nextcloud']);
		$this->assertSame(0, count($bookmarks));

		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['.com']);
		$this->assertSame(2, count($bookmarks));
	}


	public function testFindAllWithOr() {
		$bookmarks = $this->bookmarkMapper->findAll($this->userId, ['wikipedia', 'nextcloud'], 'or');
		$this->assertSame(2, count($bookmarks));
	}

	public function testFindByTag() {
		$bookmarks = $this->bookmarkMapper->findByTag($this->userId, 'one');
		$this->assertSame(3, count($bookmarks));
	}

	public function testFindByTags() {
		$bookmarks = $this->bookmarkMapper->findByTags($this->userId, ['one', 'three']);
		$this->assertSame(1, count($bookmarks));
	}

	/**
	 * @return array
	 */
	public function singleBookmarksProvider() {
		return array_map(function ($data) {
			return [$data[0], Db\Bookmark::fromArray($data[1])];
		}, [
			[['one'], ['url' => 'https://google.com/', 'title' => 'Google', 'description' => 'Search engine']],
			[['two'], ['url' => 'https://nextcloud.com/', 'title' => 'Nextcloud']],
			[['three', 'one'], ['url' => 'https://php.net/']],
			[['two', 'four', 'one'], ['url' => 'https://de.wikipedia.org/wiki/%C3%9C']],
		]);
	}
}
