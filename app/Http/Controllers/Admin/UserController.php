<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ContactInfo;
use App\Models\PersonalInfo;
use App\Models\ProfessionalDetails;
use App\Models\JobInfo;
use App\Models\CompensationInfo;
use App\Models\AccountInformation;
use App\Models\AdditionalInfo;
use App\Models\Department;
use App\Models\Shift;
use App\Models\Role;
use App\Models\OverTime;
use App\Models\LeaveBalance;
use Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Mail;
use App\Models\UserAttachment;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $all_users = User::with(['shift', 'department','forklift','role','overTime','location','attachments'])->where('role_id','!=',1)->get();

        return response()->json(['all_users'=>$all_users]);

    }



    public function create(Request $request)
    {

        $imagePath = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('profile'), $fileName);
                $imagePath = 'profile/' . $fileName;
            }
        }

        User::create([
            'uu_id' => $request->employee_id,
            'name' => $request->first_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'profile_image' => $imagePath,
            'gender' => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'national_id' => $request->national_id,
            'job_title' => $request->job_title,
            'password' => Hash::make($request->password),
            'department_id' => $request->department_id,
            'manager_id' => $request->manager_id,
            'supervisor_id' => $request->supervisor_id,
            'shift_id' => $request->shift_id,
            'status' => 1,
            'role_id' => $request->role_id,
            'location_id' => $request->location_id,
            'forklift_id' => $request->forklift_id,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file->isValid()) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('user_attachments'), $fileName);
                    UserAttachment::create([
                        'user_id' => $user->id,
                        'file_path' => 'user_attachments/' . $fileName,
                    ]);
                }
            }
        }

        if($request->leave_types)
        {
            foreach ($request->leave_types as $leave) {
            LeaveBalance::create([
                'user_id'        => $user->id,
                'leave_type_id'  => $leave['leave_type_id'],
                'year'           => date('Y'),
                'total_days'     => $leave['total_days'],
                'used_days'      => 0,
                'remaining_days' => $leave['total_days'],
            ]);
        }
        }


        





        Mail::send(
            'mails.new_employee',
            [
                'name' => $request->first_name,
                'email' => $request->email,
                'password' => $request->password,
            ],
            function ($message) use ($request) { 
                $message->from('support@kuvvets.com','Lockmytimes');
                $message->to($request->email);
                $message->subject('Employee Account Created');
            }
        );


      
        $response = ['status'=>true,"message" => "Register Successfully"];
        return response($response, 200);
        

    }



    public function view($id)
    {
        $data = User::with(['shift', 'department', 'role','leaveBalance','attachments'])
                    ->where('id', $id)
                    ->firstOrFail();

        return response()->json(['data'=>$data]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        /**
         * Handle profile image upload
         */
        $imagePath = $user->profile_image;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('profile'), $fileName);
                $imagePath = 'profile/' . $fileName;
            }
        }

        /**
         * Update user data
         */
        $user->update([
            'uu_id'          => $request->employee_id,
            'name'           => $request->first_name,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'address'        => $request->address,
            'profile_image'  => $imagePath,
            'gender'         => $request->gender,
            'date_of_birth'  => $request->date_of_birth,
            'national_id'    => $request->national_id,
            'job_title'      => $request->job_title,
            'department_id'  => $request->department_id,
            'manager_id'     => $request->manager_id,
            'supervisor_id'  => $request->supervisor_id,
            'shift_id'       => $request->shift_id,
            'role_id'        => $request->role_id,
            'location_id'    => $request->location_id,
            'forklift_id'    => $request->forklift_id,
            'status'         => $request->status ?? 1,
        ]);

        /**
         * Update user attachments
         */
        if ($request->hasFile('attachments')) {

            UserAttachment::where('user_id', $user->id)->delete();

            foreach ($request->file('attachments') as $file) {
                if ($file->isValid()) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('user_attachments'), $fileName);
                    UserAttachment::create([
                        'user_id' => $user->id,
                        'file_path' => 'user_attachments/' . $fileName,
                    ]);
                }
            }
        }

        /**
         * Update password only if provided
         */
        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        /**
         * Update / Create Leave Balances
         */
        if ($request->leave_types) {
            foreach ($request->leave_types as $leave) {
                LeaveBalance::updateOrCreate(
                    [
                        'user_id'       => $user->id,
                        'leave_type_id' => $leave['leave_type_id'],
                        'year'          => date('Y'),
                    ],
                    [
                        'total_days'     => $leave['total_days'],
                        'remaining_days' => $leave['total_days'],
                    ]
                );
            }
        }

        /**
         * Optional: Send email only if password changed
         */
        // if ($request->filled('password')) {
        //     Mail::send(
        //         'mails.new_employee',
        //         [
        //             'name'     => $user->name,
        //             'email'    => $user->email,
        //             'password' => $request->password,
        //         ],
        //         function ($message) use ($user) {
        //             $message->from('support@kuvvets.com', 'Lockmytimes');
        //             $message->to($user->email);
        //             $message->subject('Employee Account Updated');
        //         }
        //     );
        // }

        return response([
            'status'  => true,
            'message' => 'Employee updated successfully',
        ], 200);
    }

    

    public function changeStatus($id)
    {
        $status = User::where('id',$id)->first();

        if($status->status == 1)
        {
            $status->status = 0;
        }
        else
        {
            $status->status = 1;
        }
        $status->save();

        $response = ['status'=>true,"message" => "Status Changed Successfully!"];
        return response($response, 200);

    }

    public function delete(Request $request, $id)
    {
        User::find($id)->delete();

       
        $response = ['status'=>true,"message" => "User Deleted Successfully"];
        return response($response, 200);
            
    }

    public function attachment_delete(Request $request, $id)
    {
        $attachment = UserAttachment::find($id);
        if ($attachment) {
            $attachment->delete();
            return response()->json(['status' => true, 'message' => 'Attachment deleted successfully.']);
        }
        return response()->json(['status' => false, 'message' => 'Attachment not found.'], 404);
    }

}
