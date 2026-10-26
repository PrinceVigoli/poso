<span class="profile-avatar {{ $avatarClass ?? '' }}" style="width:{{ $avatarSize ?? 40 }}px;height:{{ $avatarSize ?? 40 }}px;border-radius:50%;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;background:#E9F0F9;color:#0F3D73;vertical-align:middle" aria-hidden="true">
    @if(auth()->user()->profile_photo_path)
        <img src="{{ route('profile.photo', ['v' => substr(sha1(auth()->user()->profile_photo_path), 0, 12)]) }}" alt="" width="{{ $avatarSize ?? 40 }}" height="{{ $avatarSize ?? 40 }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
    @else
        <span>{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
    @endif
</span>
