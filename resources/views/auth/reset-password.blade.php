<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Admin Panel</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('backend/assets/images/logos/favicon.png') }}" />
    <link href="{{ asset('backend/assets/css/pace.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('backend/assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('backend/assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('backend/assets/css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('backend/assets/css/icons.css') }}" rel="stylesheet">
</head>

<body class="bg-login">
    <div class="wrapper">
        <div class="section-authentication-signin d-flex align-items-center justify-content-center my-5 my-lg-0">
            <div class="container-fluid">
                <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3">
                    <div class="col mx-auto">
                        <div class="card">
                            <div class="card-body">
                                <div class="border p-4 rounded">
                                    <div class="text-center mb-4">
                                        <img src="{{ asset('backend/assets/images/logo-icon.png') }}" width="80" alt="" />
                                        <h3 class="mt-3">Reset Password</h3>
                                        <p class="text-muted">Your new password must be different from previously used passwords</p>
                                    </div>

                                    <div class="form-body">
                                        <form class="row g-3" method="POST" action="{{ route('admin.reset.password.update') }}">
                                            @csrf
                                            
                                            <!-- Password Reset Token -->
                                            <input type="hidden" name="token" value="{{ $token }}">

                                            <div class="col-12">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" 
                                                       class="form-control @error('email') is-invalid @enderror" 
                                                       id="email" 
                                                       name="email" 
                                                       value="{{ $email ?? old('email') }}" 
                                                       placeholder="admin@example.com" 
                                                       required 
                                                       autofocus>
                                                @error('email')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>

                                            <div class="col-12">
                                                <label for="password" class="form-label">New Password</label>
                                                <div class="input-group" id="show_hide_password">
                                                    <input type="password" 
                                                           class="form-control border-end-0 @error('password') is-invalid @enderror" 
                                                           id="password" 
                                                           name="password" 
                                                           placeholder="Enter new password" 
                                                           required>
                                                    <a href="javascript:;" class="input-group-text bg-transparent">
                                                        <i class='bx bx-hide'></i>
                                                    </a>
                                                </div>
                                                @error('password')
                                                    <span class="invalid-feedback d-block" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>

                                            <div class="col-12">
                                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                                <div class="input-group" id="show_hide_password_confirm">
                                                    <input type="password" 
                                                           class="form-control border-end-0" 
                                                           id="password_confirmation" 
                                                           name="password_confirmation" 
                                                           placeholder="Confirm new password" 
                                                           required>
                                                    <a href="javascript:;" class="input-group-text bg-transparent">
                                                        <i class='bx bx-hide'></i>
                                                    </a>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="bx bx-info-circle"></i>
                                                    Password must be at least 8 characters long
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bx bx-lock"></i> Set New Password
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="col-12 text-center">
                                                <p class="mb-0">
                                                    <a href="{{ route('admin.login') }}">Back to login</a>
                                                </p>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('backend/assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $("#show_hide_password a").on('click', function (event) {
                event.preventDefault();
                var input = $('#password');
                var icon = $(this).find('i');
                
                if (input.attr("type") == "text") {
                    input.attr('type', 'password');
                    icon.addClass("bx-hide").removeClass("bx-show");
                } else {
                    input.attr('type', 'text');
                    icon.removeClass("bx-hide").addClass("bx-show");
                }
            });

            $("#show_hide_password_confirm a").on('click', function (event) {
                event.preventDefault();
                var input = $('#password_confirmation');
                var icon = $(this).find('i');
                
                if (input.attr("type") == "text") {
                    input.attr('type', 'password');
                    icon.addClass("bx-hide").removeClass("bx-show");
                } else {
                    input.attr('type', 'text');
                    icon.removeClass("bx-hide").addClass("bx-show");
                }
            });
        });
    </script>
</body>
</html>