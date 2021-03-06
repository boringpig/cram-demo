<?php


namespace App\Repositories;

use App\Models\User;
use Carbon\Carbon;
use Hash;
use Intervention\Image\Facades\Image;
use Storage;

class UserRepository extends AbstractRepository
{

	/** @var User $model Model物件 */
	protected $model;

	/**
	 * UserRepository constructor.
	 *
	 * @param $model
	 */
	public function __construct(User $model)
	{
		$this->model = $model;
		parent::__construct();
	}

	/*****************************後台管理********************************

	/**
	 * 手動新增使用者
	 *
	 * @param array $data
	 * @return User
	 */
	public function createUser(array $data)
	{
		$status = isset($data['status']) ? $data['status'] : 0;
		$user = new User();
		$user->name = $data['name'];
		$user->email = $data['email'];
		$user->password = bcrypt($data['password']);
		$user->status = $status;
		$user->avatar = 'avatars/default.jpg';
		$user->save();
		if (isset($data['roles'])) {
			$user->roles()->sync($data['roles']);
		} else {
			$user->roles()->sync(array());
		}

		return $user;
	}

	/**
	 * 手動更新使用者
	 *
	 * @param array $data
	 * @param int $id
	 * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null|static|static[]
	 */
	public function updateUser(array $data, int $id)
	{
		$status = isset($data['status']) ? $data['status'] : 0;
		$user = $this->model->find($id);
		$user->name = $data['name'];
		$user->email = $data['email'];
		$user->status = $status;
		$user->save();
		if (isset($data['roles'])) {
			$user->roles()->sync($data['roles']);
		} else {
			$user->roles()->sync(array());
		}

		return $user;
	}

	/**
	 * 查詢全部工讀生
	 *
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 */
	public function getAllServitor()
	{
		$servitors = $this->model->with('roles')->whereHas('roles', function ($role_query) {
			$role_query->where('name', '=', '工讀生');
		})->with('clockIns')->paginate(8);

		return $servitors;
	}

	/**
	 * 查詢個人打卡記錄
	 *
	 * @param int $id
	 * @return mixed
	 */
	public function getServitorClockInLog(int $id)
	{
		$servitor = $this->model->find($id);

		return $servitor->clockins()->latest('on_duty')->with('works')->paginate(8);
	}

	/**
	 * 查詢各工讀生當月總時數
	 *
	 * @param array $data
	 * @return array
	 */
	public function getAllServitorClockMonth(array $data)
	{
		$servitors = $this->model->with('roles')->whereHas('roles', function ($role_query) {
			$role_query->where('name', '=', '工讀生');
		})->with('clockIns')->get();

		$result = [];
		$total_hour = 0;
		$normal = 0;
		$math_tutor = 0;
		$eng_tutor = 0;
		$read_tutor = 0;

		foreach ($servitors as $servitor) {
			$cards = $servitor->clockins()->whereMonth('on_duty', '=', $data['month'])->get();
			foreach ($cards as $card) {
				$total_hour = $total_hour + $card->total_hour;
				foreach ($card->works()->get() as $work) {
					if ($work->name == '一般工讀') {
						$normal = $normal + $work->pivot->hour;
					} elseif ($work->name == '數學家教') {
						$math_tutor = $math_tutor + $work->pivot->hour;
					} elseif ($work->name == '英文家教') {
						$eng_tutor = $eng_tutor + $work->pivot->hour;
					} elseif ($work->name == '一對一陪讀') {
						$read_tutor = $read_tutor + $work->pivot->hour;
					}
				}
			}

			$result[] = (object)[
				'姓名'    => $servitor->name,
				'總時數'   => $total_hour,
				'一般工讀'  => $normal,
				'數學家教'  => $math_tutor,
				'英文家教'  => $eng_tutor,
				'一對一陪讀' => $read_tutor
			];

			$total_hour = 0;
			$normal = 0;
			$math_tutor = 0;
			$eng_tutor = 0;
			$read_tutor = 0;
		}

		return $result;
	}

	/**
	 * 用LIKE查詢老師
	 *
	 * @return \Illuminate\Database\Eloquent\Collection|static[]
	 */
	public function getAllTeacher()
	{
		$teachers = $this->model->where('status', true)->with('roles')->whereHas('roles', function ($role_query) {
			$role_query->where('name', 'LIKE', '%老師');
		})->get();

		return $teachers;
	}

	/*****************************前台管理*******************************
	 *
	 * /**
	 * 查詢個人大頭貼
	 *
	 * @param int $user_id
	 * @return mixed|string
	 */
	public function getUserAvatarById(int $user_id)
	{
		$user = $this->model->find($user_id);
		$s3 = Storage::cloud();
		if ($s3->has($user->avatar)) {
			return $s3->url($user->avatar);
		}

		return $user->avatar;
	}


	/**
	 * 上傳更新的大頭貼
	 *
	 * @param array $data
	 * @param User $user
	 * @return User
	 */
	public function uploadUserAvatar(array $data, User $user)
	{
		$file = $data['avatar'];
		$fileName = $user->id . '.jpg';
		$image = (string)Image::make($file)->encode('jpg', 75)->resize(300, 300);
		$filePath = 'avatars/' . $fileName;
		$s3 = Storage::cloud();
		//判斷是否有此使用者的大頭貼
		if ($s3->has($filePath)) {
			//有的話修改此大頭貼
			$s3->delete($filePath);
			$s3->put($filePath, $image, 'public');
		} else {
			//沒有的話儲存大頭貼
			$s3->put($filePath, $image, 'public');
		}

		$user->avatar = $filePath;
		$user->save();

		return $user;
	}

	/**
	 * 更新使用者密碼
	 *
	 * @param array $data
	 * @param User $userObj
	 * @return bool
	 */
	public function updateUserPassword(array $data, User $userObj) : bool
	{
		if (Hash::check($data['current_password'], $userObj->password)) {
			$userObj->update([
				'password' => bcrypt($data['new_password'])
			]);

			return true;
		} else {
			return false;
		}
	}

	/**
	 * 建立應用程式登入的使用者
	 *
	 * @param string $type
	 * @param $userObj
	 * @return User
	 */
	public function findOrCreateSocialUser(string $type, $userObj): User
	{
		$user = $this->model
			->where('account_type', $type)
			->where('sns_acc_id', $userObj->id)
			->first();
		if ($user) {
			return $user;
		}

		$user = $this->model->create([
			'name'         => isset($userObj->name) ? $userObj->name : '',
			'email'        => isset($userObj->email) ? $userObj->email : '',
			'avatar'       => isset($userObj->avatar) ? $userObj->avatar : 'avatars/default.jpg',
			'sns_acc_id'   => $userObj->id,
			'account_type' => $type,
			'status'       => 1
		]);

		return $user;
	}

	/**
	 * 查詢使用者最近打卡記錄
	 *
	 * @param int $user_id
	 * @return mixed
	 */
	public function getUserLatestClock(int $user_id)
	{
		$user = $this->model->find($user_id);

		return $user->clockins()->latest('on_duty')->first();
	}

	/**
	 * 查詢使用者最近全部的打卡記錄
	 *
	 * @param int $user_id
	 * @return mixed
	 */
	public function getUserAllClockCardLatest(int $user_id)
	{
		$user = $this->model->find($user_id);

		return $user->clockins()->latest()->paginate(5);
	}

	/**
	 * 查詢使用者可選擇月份
	 *
	 * @param int $user_id
	 * @return array
	 */
	public function getUserSelectMonth(int $user_id)
	{
		$user = $this->model->find($user_id);
		$cards = $user->clockins()->get();
		$months = [];

		foreach ($cards as $card) {
			$dt = new Carbon($card->on_duty);
			$cnt = count($months);
			if ($cnt == 0) {
				array_push($months, $dt->month);
			} else {
				if (!(in_array($dt->month, $months))) {
					array_push($months, $dt->month);
				}
			}
		}

		return $months;
	}

	/**
	 * 查詢月份的使用者打卡記錄
	 *
	 * @param array $data
	 * @param int $user_id
	 * @return array
	 */
	public function getUserClockMonth(array $data, int $user_id)
	{
		$user = $this->model->find($user_id);
		$cards = $user->clockins()->whereMonth('on_duty', '=', $data['month'])->get();
		$total_hour = 0;
		$normal = 0;
		$math_tutor = 0;
		$eng_tutor = 0;
		$read_tutor = 0;

		foreach ($cards as $card) {
			$total_hour = $total_hour + $card->total_hour;

			foreach ($card->works()->get() as $work) {
				if ($work->name == '一般工讀') {
					$normal = $normal + $work->pivot->hour;
				} elseif ($work->name == '數學家教') {
					$math_tutor = $math_tutor + $work->pivot->hour;
				} elseif ($work->name == '英文家教') {
					$eng_tutor = $eng_tutor + $work->pivot->hour;
				} elseif ($work->name == '一對一陪讀') {
					$read_tutor = $read_tutor + $work->pivot->hour;
				}
			}
		}
		$month_works = [
			'總時數'  => $total_hour,
			'一般工讀' => $normal,
			'數學家教' => $math_tutor,
			'英文家教' => $eng_tutor,
			'一對一陪讀' => $read_tutor
		];

		return $month_works;
	}

}