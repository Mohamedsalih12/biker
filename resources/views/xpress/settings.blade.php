@extends('layouts.xpress')
@section('title', 'Settings')
@section('content')
    <h3>Site Settings</h3>
    <p>Manage your site settings here.</p>
    <div class="row">
        <x-session-message />
        <div class="col-md-12 grid-margin">
            
            <form method="POST" action="{{ route('xpress.settings') }}">
                @csrf
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h4 class="m-0 font-weight-bold text-white">Website - General Settings</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="website_name">Website Name</label>
                                <input type="text" class="form-control" name="website_name" value="{{ old('website_name', $settings->website_name ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="website_url">Website URL</label>
                                <input type="text" class="form-control" name="website_url" value="{{ old('website_url', $settings->website_url ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="page_title">Page Title</label>
                                <input type="text" class="form-control" name="page_title" value="{{ old('page_title', $settings->page_title ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="meta_keyword">Meta Keywords</label>
                                <input type="text" class="form-control" name="meta_keyword" value="{{ old('meta_keyword', $settings->meta_keyword ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="meta_description">Meta Description</label>
                                <input type="text" class="form-control" name="meta_description" value="{{ old('meta_description', $settings->meta_description ?? '') }}">
                            </div>                                    
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h4 class="m-0 font-weight-bold text-white">Website - Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="address">Address</label>
                                <textarea name="address" class="form-control">{{ $settings->address ?? '' }}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone_number_1">Phone Number 1</label>
                                <input type="text" class="form-control" name="phone_number_1" value="{{ old('phone_number_1', $settings->phone_number_1 ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone_number_2">Phone Number 2</label>
                                <input type="text" class="form-control" name="phone_number_2" value="{{ old('phone_number_2', $settings->phone_number_2 ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email_1">Email ID 1</label>
                                <input type="text" class="form-control" name="email_1" value="{{ old('email_1', $settings->email_1 ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email_2">Email ID 2</label>
                                <input type="text" class="form-control" name="email_2" value="{{ old('email_2', $settings->email_2 ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h4 class="m-0 font-weight-bold text-white">Website - Social Media</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="facebook">Facebook (Optional)</label>
                                <input type="text" class="form-control" name="facebook" value="{{ old('facebook', $settings->facebook ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="twitter">Twitter (Optional)</label>
                                <input type="text" class="form-control" name="twitter" value="{{ old('twitter', $settings->twitter ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="instagram">Instagram (Optional)</label>
                                <input type="text" class="form-control" name="instagram" value="{{ old('instagram', $settings->instagram ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="linkedin">LinkedIn (Optional)</label>
                                <input type="text" class="form-control" name="linkedin" value="{{ old('linkedin', $settings->linkedin ?? '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="youtube">Youtube (Optional)</label>
                                <input type="text" class="form-control" name="youtube" value="{{ old('youtube', $settings->youtube ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Save Settings</button>
            </form>
        </div>
    </div>
@endsection