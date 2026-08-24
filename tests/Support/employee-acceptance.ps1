[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('Start', 'AddDependency', 'AssignRole', 'Stop')]
    [string]$Action,

    [Parameter(Mandatory = $true)]
    [string]$StateFile,

    [Parameter(Mandatory = $true)]
    [switch]$EnableDisposableMariaDb,

    [switch]$SmokeTest,

    [string]$Employee,

    [ValidateSet('view-only', 'no-permission')]
    [string]$RoleAlias,

    [ValidateSet('hop_dong', 'cham_cong', 'nghi_phep', 'luong', 'lich_su_he_so_luong')]
    [string]$Dependency
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$script:boundParameterNames = @($PSBoundParameters.Keys)

$repoRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$testingRoot = [IO.Path]::GetFullPath((Join-Path $repoRoot 'storage\framework\testing'))
$tempParent = [IO.Path]::GetFullPath((Join-Path $testingRoot 'employee-acceptance'))
$routerPath = [IO.Path]::GetFullPath((Join-Path $repoRoot 'tests\Support\employee-acceptance-router.php'))
$publicPath = [IO.Path]::GetFullPath((Join-Path $repoRoot 'public'))
$publicStoragePath = [IO.Path]::GetFullPath((Join-Path $publicPath 'storage'))
$storageTargetPath = [IO.Path]::GetFullPath((Join-Path $repoRoot 'storage\app\public'))
$phpCommand = Get-Command php -ErrorAction Stop
$phpExecutable = [IO.Path]::GetFullPath($phpCommand.Source)
$managedEnvironmentNames = @(
    'MARIADB_TEST_ENABLED', 'MARIADB_TEST_DATABASE', 'MARIADB_TEST_HOST', 'MARIADB_TEST_PORT',
    'MARIADB_TEST_USERNAME', 'MARIADB_TEST_PASSWORD', 'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'APP_URL',
    'APP_TIMEZONE', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'DB_CONNECTION', 'DB_URL', 'DB_HOST',
    'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET', 'DB_TIMEZONE',
    'NHAN_VIEN_MODULE_ENABLED', 'EMPLOYEE_AVATAR_PREFIX', 'EMPLOYEE_ACCEPTANCE_RUN_ID',
    'EMPLOYEE_ACCEPTANCE_MA_NV', 'EMPLOYEE_ACCEPTANCE_ROLE_ALIAS', 'EMPLOYEE_ACCEPTANCE_DEPENDENCY',
    'EMPLOYEE_ACCEPTANCE_TEST_STORAGE_LINK_MARKER_FAIL',
    'EMPLOYEE_ACCEPTANCE_TEST_LOCK_READY_LEAF', 'EMPLOYEE_ACCEPTANCE_TEST_LOCK_RELEASE_LEAF',
    'EMPLOYEE_ACCEPTANCE_TEST_STATE_SWAP_READY_LEAF', 'EMPLOYEE_ACCEPTANCE_TEST_STATE_SWAP_RELEASE_LEAF',
    'SESSION_DRIVER', 'CACHE_STORE', 'QUEUE_CONNECTION', 'LOG_CHANNEL'
)
$environmentSnapshot = @{}
$claimedState = $false
$statePath = $null
$stateOwnerMarker = $null
$stateClaimOwnerMarker = $null
$databaseName = $null
$runId = $null
$ownedRoot = $null
$ownedProcess = $null
$ownedProcessIdentity = $null
$ownedCommandTokens = $null
$ownedStorageLink = $false
$ownedStorageLinkIdentity = $null
$currentInvocationStorageLink = $false
$actionFailure = $null
$linkFailureDetail = $null
$identityError = $null
$stateLease = $null
$stateParentLease = $null
$stateLockLease = $null
$stateLockLeaf = $null
$stateRemoved = $false
$ownedParentLease = $null
$ownedRootLease = $null
$stateDeleteRequested = $false
$cleanupErrors = @()

function Add-CleanupError([string]$Code) {
    $allowed = @(
        'PROCESS_CLEANUP_FAILED', 'CHILD_ENVIRONMENT_FAILED', 'UPLOAD_CLEANUP_FAILED',
        'DATABASE_CLEANUP_FAILED', 'LINK_CLEANUP_FAILED', 'TEMP_CLEANUP_FAILED',
        'STATE_CLEANUP_FAILED', 'START_CLEANUP_FAILED', 'OWNED_LEASE_CLOSE_FAILED',
        'STATE_LEASE_CLOSE_FAILED', 'CLAIMED_STATE_CLEANUP_FAILED', 'RESTORE_ENVIRONMENT_FAILED'
    )
    if ($allowed -notcontains $Code) { throw 'CLEANUP_ERROR_NOT_ALLOWLISTED' }
    $script:cleanupErrors += $Code
}

function Ensure-NativeHelpers {
    if ($null -ne ('EmployeeAcceptance.NativeMethods' -as [type])) { return }
    Add-Type -TypeDefinition @'
using System;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;
using Microsoft.Win32.SafeHandles;

namespace EmployeeAcceptance {
    public static class NativeMethods {
        private const uint FILE_FLAG_BACKUP_SEMANTICS = 0x02000000;
        private const uint FILE_FLAG_OPEN_REPARSE_POINT = 0x00200000;
        private const int FILE_RENAME_INFO = 3;
        private const int FILE_RENAME_INFO_EX = 22;
        private const int FILE_RENAME_INFORMATION_EX = 65;
        private const uint FILE_RENAME_REPLACE_IF_EXISTS = 0x1;
        private const uint FILE_RENAME_POSIX_SEMANTICS = 0x2;
        private const uint OBJ_DONT_REPARSE = 0x00001000;
        private const uint FILE_NON_DIRECTORY_FILE = 0x00000040;
        private const uint FILE_SYNCHRONOUS_IO_NONALERT = 0x00000020;
        private const uint FILE_OPEN = 1;
        private const uint FILE_CREATE = 2;
        private const uint FILE_SHARE_READ = 1;
        private const uint FILE_SHARE_WRITE = 2;
        private const uint FILE_SHARE_DELETE = 4;
        private const uint FILE_SHARE_NONE = 0;
        private const uint STATE_SHARE_ACCESS = FILE_SHARE_READ | FILE_SHARE_DELETE;
        [StructLayout(LayoutKind.Sequential)]
        private struct ByHandleFileInformation {
            public uint FileAttributes;
            public System.Runtime.InteropServices.ComTypes.FILETIME CreationTime;
            public System.Runtime.InteropServices.ComTypes.FILETIME LastAccessTime;
            public System.Runtime.InteropServices.ComTypes.FILETIME LastWriteTime;
            public uint VolumeSerialNumber;
            public uint FileSizeHigh;
            public uint FileSizeLow;
            public uint NumberOfLinks;
            public uint FileIndexHigh;
            public uint FileIndexLow;
        }

        [StructLayout(LayoutKind.Sequential, Pack = 1)]
        private struct FileIdInfo {
            public ulong VolumeSerialNumber;
            public ulong FileIdLow;
            public ulong FileIdHigh;
        }

        [DllImport("kernel32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        private static extern SafeFileHandle CreateFile(
            string fileName, uint desiredAccess, uint shareMode, IntPtr securityAttributes,
            uint creationDisposition, uint flagsAndAttributes, IntPtr templateFile);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool GetFileInformationByHandle(
            SafeFileHandle file, out ByHandleFileInformation information);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool GetFileInformationByHandleEx(
            SafeFileHandle file, int fileInformationClass, out FileIdInfo information, uint bufferSize);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool SetFileInformationByHandle(
            SafeFileHandle file, int fileInformationClass, IntPtr fileInformation, uint bufferSize);

        [StructLayout(LayoutKind.Sequential)]
        private struct IoStatusBlock { public IntPtr Status; public IntPtr Information; }

        [StructLayout(LayoutKind.Sequential)]
        private struct UnicodeString { public ushort Length; public ushort MaximumLength; public IntPtr Buffer; }

        [StructLayout(LayoutKind.Sequential)]
        private struct ObjectAttributes {
            public uint Length; public IntPtr RootDirectory; public IntPtr ObjectName;
            public uint Attributes; public IntPtr SecurityDescriptor; public IntPtr SecurityQualityOfService;
        }

        [DllImport("ntdll.dll")]
        private static extern int NtCreateFile(
            out IntPtr fileHandle, uint desiredAccess, ref ObjectAttributes objectAttributes,
            out IoStatusBlock ioStatusBlock, IntPtr allocationSize, uint fileAttributes,
            uint shareAccess, uint createDisposition, uint createOptions, IntPtr eaBuffer, uint eaLength);

        [DllImport("ntdll.dll")]
        private static extern int NtSetInformationFile(
            SafeFileHandle file, out IoStatusBlock ioStatus, IntPtr fileInformation, uint length, int fileInformationClass);

        [DllImport("shell32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
        private static extern IntPtr CommandLineToArgvW(string commandLine, out int argumentCount);

        [DllImport("kernel32.dll")]
        private static extern IntPtr LocalFree(IntPtr memory);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern IntPtr GetCurrentProcess();

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool CloseHandle(IntPtr handle);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool DuplicateHandle(
            IntPtr sourceProcessHandle, SafeFileHandle sourceHandle,
            IntPtr targetProcessHandle, out IntPtr targetHandle,
            uint desiredAccess, bool inheritHandle, uint options);

        public sealed class Lease : IDisposable {
            private SafeFileHandle handle;
            public string Identity { get; private set; }
            public bool IsReparse { get; private set; }
            public bool IsDirectory { get; private set; }

            private Lease(SafeFileHandle value, ByHandleFileInformation information) {
                handle = value;
                Update(information);
            }

            private void Update(ByHandleFileInformation information) {
                IsReparse = (information.FileAttributes & 0x400) != 0;
                IsDirectory = (information.FileAttributes & 0x10) != 0;
                Identity = information.VolumeSerialNumber.ToString("X8") + ":" + information.FileIndexHigh.ToString("X8") + information.FileIndexLow.ToString("X8");
            }

            public static Lease Open(string path, bool allowReparse) {
                SafeFileHandle value = CreateFile(path, 0x00100081, 0x00000003, IntPtr.Zero, 3, FILE_FLAG_BACKUP_SEMANTICS | FILE_FLAG_OPEN_REPARSE_POINT, IntPtr.Zero);
                if (value == null || value.IsInvalid) throw new InvalidOperationException("NATIVE_LEASE_OPEN_FAILED");
                ByHandleFileInformation information;
                if (!GetFileInformationByHandle(value, out information)) { value.Dispose(); throw new InvalidOperationException("NATIVE_LEASE_IDENTITY_FAILED"); }
                if ((information.FileAttributes & 0x400) != 0 && !allowReparse) { value.Dispose(); throw new InvalidOperationException("NATIVE_REPARSE_REJECTED"); }
                return new Lease(value, information);
            }

            public static Lease OpenForDelete(string path, bool allowReparse) {
                SafeFileHandle value = CreateFile(path, 0x00110083, 0x00000003, IntPtr.Zero, 3, FILE_FLAG_BACKUP_SEMANTICS | FILE_FLAG_OPEN_REPARSE_POINT, IntPtr.Zero);
                if (value == null || value.IsInvalid) throw new InvalidOperationException("NATIVE_LEASE_OPEN_FAILED");
                ByHandleFileInformation information;
                if (!GetFileInformationByHandle(value, out information)) { value.Dispose(); throw new InvalidOperationException("NATIVE_LEASE_IDENTITY_FAILED"); }
                if ((information.FileAttributes & 0x400) != 0 && !allowReparse) { value.Dispose(); throw new InvalidOperationException("NATIVE_REPARSE_REJECTED"); }
                return new Lease(value, information);
            }

            private static void ValidateLeaf(string leaf) {
                if (String.IsNullOrEmpty(leaf) || leaf == "." || leaf == ".." ||
                    leaf.EndsWith(".", StringComparison.Ordinal) ||
                    leaf.EndsWith(" ", StringComparison.Ordinal) ||
                    leaf.IndexOfAny(new char[] { '\\', '/', ':', '*', '?', '"', '<', '>', '|' }) >= 0) {
                    throw new InvalidOperationException("NATIVE_RELATIVE_LEAF_INVALID");
                }
            }

            private static Lease OpenRelativeInternal(Lease parent, string leaf, uint disposition, bool allowReparse, uint shareAccess, bool lockLeaf, uint desiredAccess) {
                if (parent == null || parent.handle == null) throw new InvalidOperationException("NATIVE_RELATIVE_PARENT_INVALID");
                ValidateLeaf(leaf);
                IntPtr nameMemory = IntPtr.Zero;
                IntPtr objectNameMemory = IntPtr.Zero;
                IntPtr rawHandle = IntPtr.Zero;
                try {
                    byte[] name = Encoding.Unicode.GetBytes(leaf);
                    nameMemory = Marshal.AllocHGlobal(name.Length + 2);
                    Marshal.Copy(name, 0, nameMemory, name.Length);
                    Marshal.WriteInt16(nameMemory, name.Length, 0);
                    UnicodeString unicode = new UnicodeString {
                        Length = (ushort)name.Length,
                        MaximumLength = (ushort)(name.Length + 2),
                        Buffer = nameMemory,
                    };
                    objectNameMemory = Marshal.AllocHGlobal(Marshal.SizeOf(typeof(UnicodeString)));
                    Marshal.StructureToPtr(unicode, objectNameMemory, false);
                    ObjectAttributes attributes = new ObjectAttributes {
                        Length = (uint)Marshal.SizeOf(typeof(ObjectAttributes)),
                        RootDirectory = parent.handle.DangerousGetHandle(),
                        ObjectName = objectNameMemory,
                        Attributes = OBJ_DONT_REPARSE,
                        SecurityDescriptor = IntPtr.Zero,
                        SecurityQualityOfService = IntPtr.Zero,
                    };
                    IoStatusBlock io;
                    int status = NtCreateFile(
                        out rawHandle,
                        desiredAccess,
                        ref attributes,
                        out io,
                        IntPtr.Zero,
                        0,
                        shareAccess,
                        disposition,
                        FILE_NON_DIRECTORY_FILE | FILE_FLAG_OPEN_REPARSE_POINT | FILE_SYNCHRONOUS_IO_NONALERT,
                        IntPtr.Zero,
                        0);
                    if (status < 0 || rawHandle == IntPtr.Zero || rawHandle == new IntPtr(-1)) {
                        if (lockLeaf && (status == unchecked((int)0xC0000035) ||
                            status == unchecked((int)0xC0000056) ||
                            status == unchecked((int)0xC0000043))) {
                            throw new InvalidOperationException("NATIVE_STATE_LOCKED");
                        }
                        throw new InvalidOperationException("NATIVE_RELATIVE_OPEN_FAILED_" + status.ToString("X8"));
                    }
                    SafeFileHandle value = new SafeFileHandle(rawHandle, true);
                    rawHandle = IntPtr.Zero;
                    ByHandleFileInformation information;
                    if (!GetFileInformationByHandle(value, out information)) { value.Dispose(); throw new InvalidOperationException("NATIVE_LEASE_IDENTITY_FAILED"); }
                    if ((information.FileAttributes & 0x400) != 0 && !allowReparse) { value.Dispose(); throw new InvalidOperationException("NATIVE_REPARSE_REJECTED"); }
                    if ((information.FileAttributes & 0x10) != 0) { value.Dispose(); throw new InvalidOperationException("NATIVE_RELATIVE_DIRECTORY_REJECTED"); }
                    return new Lease(value, information);
                } finally {
                    if (rawHandle != IntPtr.Zero && rawHandle != new IntPtr(-1)) { CloseHandle(rawHandle); }
                    if (objectNameMemory != IntPtr.Zero) { Marshal.FreeHGlobal(objectNameMemory); }
                    if (nameMemory != IntPtr.Zero) { Marshal.FreeHGlobal(nameMemory); }
                }
            }

            public static Lease CreateNewRelative(Lease parent, string leaf) {
                return OpenRelativeInternal(parent, leaf, FILE_CREATE, false, FILE_SHARE_READ | FILE_SHARE_WRITE, false, 0x00110083);
            }

            public static Lease OpenRelative(Lease parent, string leaf, bool allowReparse) {
                return OpenRelativeInternal(parent, leaf, FILE_OPEN, allowReparse, FILE_SHARE_READ | FILE_SHARE_WRITE, false, 0x00110083);
            }

            public static Lease CreateStateRelative(Lease parent, string leaf) {
                return OpenRelativeInternal(parent, leaf, FILE_CREATE, false, STATE_SHARE_ACCESS, false, 0x00110083);
            }

            public static Lease OpenStateRelative(Lease parent, string leaf) {
                return OpenRelativeInternal(parent, leaf, FILE_OPEN, false, STATE_SHARE_ACCESS, false, 0x00110083);
            }

            public static Lease OpenStateVerifyRelative(Lease parent, string leaf) {
                return OpenRelativeInternal(parent, leaf, FILE_OPEN, false, FILE_SHARE_READ | FILE_SHARE_WRITE | FILE_SHARE_DELETE, false, 0x00100081);
            }

            public static Lease CreateInvocationLockRelative(Lease parent, string leaf) {
                return OpenRelativeInternal(parent, leaf, FILE_CREATE, false, FILE_SHARE_NONE, true, 0x00110083);
            }

            public string RefreshIdentity() {
                ByHandleFileInformation information;
                if (!GetFileInformationByHandle(handle, out information)) throw new InvalidOperationException("NATIVE_LEASE_IDENTITY_FAILED");
                Update(information);
                return Identity;
            }

            public string FullIdentity {
                get {
                    FileIdInfo information;
                    if (!GetFileInformationByHandleEx(handle, 18, out information, (uint)Marshal.SizeOf(typeof(FileIdInfo)))) {
                        throw new InvalidOperationException("NATIVE_FULL_IDENTITY_FAILED");
                    }
                    return information.VolumeSerialNumber.ToString("X16") + ":" + information.FileIdHigh.ToString("X16") + ":" + information.FileIdLow.ToString("X16");
                }
            }

            public void MarkDeleteOnClose() {
                IntPtr buffer = Marshal.AllocHGlobal(4);
                try {
                    Marshal.WriteInt32(buffer, 0, 1);
                    if (!SetFileInformationByHandle(handle, 4, buffer, 4)) {
                        throw new InvalidOperationException("NATIVE_LOCK_DISPOSITION_FAILED");
                    }
                } finally { Marshal.FreeHGlobal(buffer); }
            }

            public void RenameTo(Lease parent, string leaf) {
                if (parent == null || parent.handle == null || handle == null ||
                    String.IsNullOrEmpty(leaf) || leaf.IndexOfAny(new char[] { '\\', '/', ':', '*' }) >= 0) {
                    throw new InvalidOperationException("NATIVE_RENAME_TARGET_INVALID");
                }
                byte[] name = Encoding.Unicode.GetBytes(leaf);
                IntPtr buffer = Marshal.AllocHGlobal(20 + name.Length);
                try {
                    Marshal.WriteByte(buffer, 0, 0);
                    Marshal.WriteIntPtr(buffer, 8, parent.handle.DangerousGetHandle());
                    Marshal.WriteInt32(buffer, 16, name.Length);
                    Marshal.Copy(name, 0, IntPtr.Add(buffer, 20), name.Length);
                    if (SetFileInformationByHandle(handle, FILE_RENAME_INFO, buffer, (uint)(20 + name.Length))) return;
                    if (Marshal.GetLastWin32Error() != 87) throw new InvalidOperationException("NATIVE_QUARANTINE_RENAME_FAILED");
                    IoStatusBlock io;
                    if (NtSetInformationFile(handle, out io, buffer, (uint)(20 + name.Length), 10) != 0) throw new InvalidOperationException("NATIVE_QUARANTINE_RENAME_FAILED");
                } finally { Marshal.FreeHGlobal(buffer); }
            }

            public void ReplaceTo(Lease parent, string leaf) {
                if (parent == null || parent.handle == null || handle == null ||
                    String.IsNullOrEmpty(leaf) || leaf.IndexOfAny(new char[] { '\\', '/', ':', '*', '?', '"', '<', '>', '|' }) >= 0 ||
                    leaf == "." || leaf == "..") {
                    throw new InvalidOperationException("NATIVE_RENAME_TARGET_INVALID");
                }
                byte[] name = Encoding.Unicode.GetBytes(leaf);
                // FILE_RENAME_INFO_EX is DWORD Flags, aligned HANDLE RootDirectory,
                // DWORD FileNameLength, followed by the UTF-16 flexible array.
                IntPtr buffer = Marshal.AllocHGlobal(20 + name.Length);
                try {
                    Marshal.WriteInt32(buffer, 0, (int)(FILE_RENAME_REPLACE_IF_EXISTS | FILE_RENAME_POSIX_SEMANTICS));
                    Marshal.WriteIntPtr(buffer, 8, parent.handle.DangerousGetHandle());
                    Marshal.WriteInt32(buffer, 16, name.Length);
                    Marshal.Copy(name, 0, IntPtr.Add(buffer, 20), name.Length);
                    if (SetFileInformationByHandle(handle, FILE_RENAME_INFO_EX, buffer, (uint)(20 + name.Length))) return;
                    int win32Error = Marshal.GetLastWin32Error();
                    if (win32Error != 87) throw new InvalidOperationException("NATIVE_ATOMIC_PUBLISH_FAILED_WIN32_" + win32Error.ToString("X8"));
                    IoStatusBlock io;
                    int ntStatus = NtSetInformationFile(handle, out io, buffer, (uint)(20 + name.Length), FILE_RENAME_INFORMATION_EX);
                    if (ntStatus != 0) throw new InvalidOperationException("NATIVE_ATOMIC_PUBLISH_FAILED_NT_" + ntStatus.ToString("X8"));
                } finally { Marshal.FreeHGlobal(buffer); }
            }

            public void Delete() {
                IntPtr buffer = Marshal.AllocHGlobal(4);
                try {
                    Marshal.WriteInt32(buffer, 0, 0x13);
                    if (SetFileInformationByHandle(handle, 21, buffer, 4)) return;
                    IoStatusBlock io;
                    if (NtSetInformationFile(handle, out io, buffer, 4, 64) == 0) return;
                    Marshal.WriteInt32(buffer, 0, 1);
                    if (!SetFileInformationByHandle(handle, 4, buffer, 4)) throw new InvalidOperationException("NATIVE_DELETE_FAILED");
                } finally { Marshal.FreeHGlobal(buffer); }
            }

            public FileStream OpenStream() {
                IntPtr duplicate;
                IntPtr process = GetCurrentProcess();
                if (!DuplicateHandle(process, handle, process, out duplicate, 0, false, 0x2) || duplicate == IntPtr.Zero) {
                    throw new InvalidOperationException("NATIVE_LEASE_DUPLICATE_FAILED");
                }
                SafeFileHandle duplicateHandle = new SafeFileHandle(duplicate, true);
                try {
                    FileStream stream = new FileStream(duplicateHandle, FileAccess.ReadWrite, 4096, false);
                    stream.Position = 0;
                    return stream;
                } catch {
                    duplicateHandle.Dispose();
                    throw;
                }
            }
            public void Dispose() { if (handle != null) { handle.Dispose(); handle = null; } }
        }

        public static string[] ParseCommandLine(string commandLine) {
            int count;
            IntPtr values = CommandLineToArgvW(commandLine, out count);
            if (values == IntPtr.Zero || count < 1) throw new InvalidOperationException("ARGV_PARSE_FAILED");
            try {
                string[] result = new string[count];
                for (int index = 0; index < count; index++) {
                    IntPtr value = Marshal.ReadIntPtr(values, index * IntPtr.Size);
                    result[index] = Marshal.PtrToStringUni(value) ?? "";
                }
                return result;
            } finally {
                LocalFree(values);
            }
        }
    }
}
'@ -Language CSharp
}

Ensure-NativeHelpers

function Normalize-Path([string]$Path) {
    return [IO.Path]::GetFullPath($Path).TrimEnd([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar)
}

function Test-Reparse([string]$Path) {
    $item = Get-Item -LiteralPath $Path -Force -ErrorAction SilentlyContinue
    if ($null -eq $item) { return $false }
    return (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0)
}

function Assert-RegularChain([string]$Path, [switch]$AllowMissingLeaf) {
    $full = Normalize-Path $Path
    $cursor = $full
    while ($true) {
        $item = Get-Item -LiteralPath $cursor -Force -ErrorAction SilentlyContinue
        if ($null -ne $item) {
            if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw 'REPARSE_POINT'
            }
            if ($cursor -eq $full -and $item.PSIsContainer -and -not $AllowMissingLeaf) {
                throw 'STATE_NOT_REGULAR'
            }
        } elseif ($cursor -eq $full -and -not $AllowMissingLeaf) {
            throw 'PATH_MISSING'
        }
        $parent = [IO.Path]::GetDirectoryName($cursor)
        if ([string]::IsNullOrEmpty($parent) -or $parent -eq $cursor) { break }
        $cursor = $parent
    }
}

function Resolve-StatePath {
    if ([string]::IsNullOrWhiteSpace($StateFile) -or $StateFile.IndexOfAny([char[]]'*?[]') -ge 0) {
        throw 'STATE_PATH_INVALID'
    }
    $full = Normalize-Path ([IO.Path]::GetFullPath($StateFile, (Get-Location).Path))
    $name = [IO.Path]::GetFileName($full)
    if ($name -notmatch '^employee-acceptance(?:-[a-f0-9]+)?\.json$') {
        throw 'STATE_NAME_INVALID'
    }
    $parent = Normalize-Path ([IO.Path]::GetDirectoryName($full))
    if ($parent -cne (Normalize-Path $testingRoot)) {
        throw 'STATE_PARENT_INVALID'
    }
    Assert-RegularChain $parent -AllowMissingLeaf
    if ($null -ne (Get-Item -LiteralPath $full -Force -ErrorAction SilentlyContinue)) {
        Assert-RegularChain $full
    }
    return $full
}

function Get-StateLockLeaf {
    $name = [IO.Path]::GetFileName($statePath)
    if ($name -notmatch '^((?:employee-acceptance)(?:-[a-f0-9]+)?)\.json$') {
        throw 'STATE_NAME_INVALID'
    }
    return '.employee-acceptance-lock-' + $Matches[1] + '.lock'
}

function Enter-StateInvocationLock {
    if ($null -ne $stateLockLease) { return }
    if ($null -eq $stateParentLease) {
        $script:stateParentLease = [EmployeeAcceptance.NativeMethods+Lease]::Open((Normalize-Path $testingRoot), $false)
    }
    $script:stateLockLeaf = Get-StateLockLeaf
    $lock = $null
    try {
        $lock = [EmployeeAcceptance.NativeMethods+Lease]::CreateInvocationLockRelative($stateParentLease, $stateLockLeaf)
        $lock.MarkDeleteOnClose()
        $script:stateLockLease = $lock
        $lock = $null
        Invoke-TestLockHold
    } catch {
        if ($null -ne $lock) { try { $lock.Dispose() } catch {} }
        if ($_.Exception.Message -match 'NATIVE_STATE_LOCKED' -or
            $_.Exception.Message -match 'NATIVE_RELATIVE_OPEN_FAILED_(C0000035|C0000056|C0000043)') { throw 'STATE_LOCKED' }
        throw 'STATE_LOCK_CREATE_FAILED'
    }
}

function Invoke-TestLockHold {
    $readyLeaf = [string]$env:EMPLOYEE_ACCEPTANCE_TEST_LOCK_READY_LEAF
    $releaseLeaf = [string]$env:EMPLOYEE_ACCEPTANCE_TEST_LOCK_RELEASE_LEAF
    if ([string]::IsNullOrEmpty($readyLeaf) -and [string]::IsNullOrEmpty($releaseLeaf)) { return }
    if ($readyLeaf -notmatch '^employee-acceptance-lock-[a-f0-9]{12}-ready\.gate$' -or
        $releaseLeaf -notmatch '^employee-acceptance-lock-[a-f0-9]{12}-release\.gate$' -or
        $readyLeaf.Substring(0, $readyLeaf.Length - 11) -cne $releaseLeaf.Substring(0, $releaseLeaf.Length - 13)) {
        throw 'LOCK_TEST_GATE_INVALID'
    }
    $readyPath = Join-Path $testingRoot $readyLeaf
    $releasePath = Join-Path $testingRoot $releaseLeaf
    $readyStream = $null
    try {
        $readyStream = [IO.File]::Open($readyPath, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        $bytes = [Text.Encoding]::UTF8.GetBytes("ready`n")
        $readyStream.Write($bytes, 0, $bytes.Length); $readyStream.Flush($true)
    } finally {
        if ($null -ne $readyStream) { $readyStream.Dispose() }
    }
    try {
        $deadline = [DateTime]::UtcNow.AddSeconds(30)
        while (-not [IO.File]::Exists($releasePath) -and [DateTime]::UtcNow -lt $deadline) {
            Start-Sleep -Milliseconds 10
        }
        if (-not [IO.File]::Exists($releasePath)) { throw 'LOCK_TEST_GATE_TIMEOUT' }
    } finally {
        if ([IO.File]::Exists($readyPath)) { [IO.File]::Delete($readyPath) }
    }
}

function Invoke-TestStateSwapHold {
    $readyLeaf = [string]$env:EMPLOYEE_ACCEPTANCE_TEST_STATE_SWAP_READY_LEAF
    $releaseLeaf = [string]$env:EMPLOYEE_ACCEPTANCE_TEST_STATE_SWAP_RELEASE_LEAF
    if ([string]::IsNullOrEmpty($readyLeaf) -and [string]::IsNullOrEmpty($releaseLeaf)) { return }
    if ($readyLeaf -notmatch '^employee-acceptance-state-swap-[a-f0-9]{12}-ready\.gate$' -or
        $releaseLeaf -notmatch '^employee-acceptance-state-swap-[a-f0-9]{12}-release\.gate$') {
        throw 'STATE_SWAP_TEST_GATE_INVALID'
    }
    $readyPath = Join-Path $testingRoot $readyLeaf
    $releasePath = Join-Path $testingRoot $releaseLeaf
    $readyStream = $null
    try {
        $readyStream = [IO.File]::Open($readyPath, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
        $bytes = [Text.Encoding]::UTF8.GetBytes("ready`n")
        $readyStream.Write($bytes, 0, $bytes.Length); $readyStream.Flush($true)
    } finally {
        if ($null -ne $readyStream) { $readyStream.Dispose() }
    }
    try {
        $deadline = [DateTime]::UtcNow.AddSeconds(30)
        while (-not [IO.File]::Exists($releasePath) -and [DateTime]::UtcNow -lt $deadline) {
            Start-Sleep -Milliseconds 10
        }
        if (-not [IO.File]::Exists($releasePath)) { throw 'STATE_SWAP_TEST_GATE_TIMEOUT' }
    } finally {
        if ([IO.File]::Exists($readyPath)) { [IO.File]::Delete($readyPath) }
    }
}

function State-Marker([string]$Database, [string]$Id) {
    $bytes = [Text.Encoding]::UTF8.GetBytes("employee-acceptance|$Database|$Id")
    $hash = [Security.Cryptography.SHA256]::Create()
    try { return ([BitConverter]::ToString($hash.ComputeHash($bytes))).Replace('-', '').ToLowerInvariant() }
    finally { $hash.Dispose() }
}

function Assert-StateShape([hashtable]$State) {
    $required = @('schema', 'phase', 'owner_marker', 'database', 'run_id', 'admin_ma_nv', 'admin_email', 'port', 'owns_storage_link')
    foreach ($key in $required) {
        if (-not $State.ContainsKey($key)) { throw 'STATE_SHAPE_INVALID' }
    }
    $stateDatabase = [string]$State.database
    $stateRunId = [string]$State.run_id
    if (([int]$State.schema -ne 1) -or ([string]$State.phase -notin @('starting', 'started')) -or ($stateDatabase -notmatch '^quan_ly_nhan_su_employee_test_[a-f0-9]{12}$') -or ($stateRunId -notmatch '^[a-f0-9]{12}$') -or ($stateDatabase.Substring($stateDatabase.Length - 12) -cne $stateRunId) -or ([string]$State.owner_marker -notmatch '^[a-f0-9]{32}$') -or ([string]$State.admin_ma_nv -notmatch '^NV[0-9]{3}$') -or ([string]$State.admin_email -notmatch '^admin-[a-f0-9]{12}@example\.test$') -or ([int]$State.port -ne 8012)) {
        throw 'STATE_IDENTITY_INVALID'
    }
    $ownsLink = [bool]$State.owns_storage_link
    if ($ownsLink) {
        if (-not $State.ContainsKey('storage_link_identity') -or
            [string]$State.storage_link_identity -notmatch '^[a-f0-9]{16}:[a-f0-9]{16}:[a-f0-9]{16}$') {
            throw 'STATE_STORAGE_LINK_IDENTITY_INVALID'
        }
    } elseif ($State.ContainsKey('storage_link_identity')) {
        throw 'STATE_STORAGE_LINK_IDENTITY_INVALID'
    }
    foreach ($key in @('password', 'secret', 'key', 'hash', 'dsn', 'temp_path', 'log_path', 'upload_path')) {
        if ($State.ContainsKey($key)) { throw 'STATE_SECRET_INVALID' }
    }
    if ($State.ContainsKey('command_tokens')) {
        if (-not ($State.command_tokens -is [System.Collections.IEnumerable])) { throw 'STATE_COMMAND_INVALID' }
        foreach ($token in $State.command_tokens) {
            if ([string]$token -match '(?i)(password|secret|dsn|temp|log|upload|app_key)') { throw 'STATE_COMMAND_INVALID' }
        }
    }
}

function Open-StateLease([string]$Path) {
    Enter-StateInvocationLock
    if ($null -eq $stateParentLease) {
        $script:stateParentLease = [EmployeeAcceptance.NativeMethods+Lease]::Open((Normalize-Path $testingRoot), $false)
    }
    if ($null -eq $stateLease) {
        try { $script:stateLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenStateRelative($stateParentLease, [IO.Path]::GetFileName($Path)) }
        catch { throw 'STATE_MISSING' }
    }
}

function Read-StateText {
    Enter-StateInvocationLock
    $path = Resolve-StatePath
    Open-StateLease $path
    $stream = $null
    try {
        $stream = $stateLease.OpenStream()
        $reader = New-Object IO.StreamReader($stream, [Text.Encoding]::UTF8, $false, 4096, $true)
        $text = $reader.ReadToEnd(); $reader.Dispose()
    } finally { if ($null -ne $stream) { $stream.Dispose() } }
    return $text
}

function Read-State {
    $parsed = (Read-StateText) | ConvertFrom-Json -AsHashtable
    if (-not ($parsed -is [hashtable])) { throw 'STATE_JSON_INVALID' }
    Assert-StateShape $parsed
    return $parsed
}

function Write-AtomicState([hashtable]$State) {
    $path = if ($null -ne $statePath) { $statePath } else { Resolve-StatePath }
    $existing = (Read-StateText) | ConvertFrom-Json -AsHashtable
    if (-not ($existing -is [hashtable]) -or [string]$existing.owner_marker -notin @($stateOwnerMarker, $stateClaimOwnerMarker, [string]$State.owner_marker)) {
        throw 'STATE_OWNERSHIP_INVALID'
    }
    $oldLease = $stateLease
    $newLease = $null
    $stream = $null
    try {
        $json = $State | ConvertTo-Json -Depth 8 -Compress
        $leaf = '.employee-acceptance-state-write-' + [Guid]::NewGuid().ToString('N') + '.json'
        $newLease = [EmployeeAcceptance.NativeMethods+Lease]::CreateStateRelative($stateParentLease, $leaf)
        $stream = $newLease.OpenStream(); $stream.SetLength(0)
        $bytes = [Text.UTF8Encoding]::new($false).GetBytes($json)
        $stream.Write($bytes, 0, $bytes.Length); $stream.Flush($true)
        if ($env:EMPLOYEE_ACCEPTANCE_TEST_STATE_WRITE_DELAY_MS -match '^[1-9][0-9]{0,2}$') {
            Start-Sleep -Milliseconds ([int]$env:EMPLOYEE_ACCEPTANCE_TEST_STATE_WRITE_DELAY_MS)
        }
        $stream.Dispose(); $stream = $null
        $publishedIdentity = $newLease.FullIdentity
        $validated = (Read-LeaseText $newLease) | ConvertFrom-Json -AsHashtable
        if (-not ($validated -is [hashtable])) { throw 'STATE_JSON_INVALID' }
        Assert-StateShape $validated
        if ([string]$validated.owner_marker -cne [string]$State.owner_marker) { throw 'STATE_OWNERSHIP_INVALID' }
        $newLease.ReplaceTo($stateParentLease, [IO.Path]::GetFileName($path))
        $publishedLease = $null
        try {
            $publishedLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenStateVerifyRelative($stateParentLease, [IO.Path]::GetFileName($path))
            if ($publishedLease.IsDirectory -or $publishedLease.IsReparse -or $publishedLease.FullIdentity -cne $publishedIdentity) {
                throw 'STATE_PUBLISH_IDENTITY_INVALID'
            }
            $publishedState = (Read-LeaseText $publishedLease) | ConvertFrom-Json -AsHashtable
            if (-not ($publishedState -is [hashtable])) { throw 'STATE_JSON_INVALID' }
            Assert-StateShape $publishedState
            if ([string]$publishedState.owner_marker -cne [string]$State.owner_marker) { throw 'STATE_OWNERSHIP_INVALID' }
        } finally {
            if ($null -ne $publishedLease) { $publishedLease.Dispose() }
        }
        $script:stateLease = $newLease
        $newLease = $null
        $oldLease.Dispose()
    } catch {
        if ($null -ne $stream) { try { $stream.Dispose() } catch {} ; $stream = $null }
        if ($null -ne $newLease) {
            try { $newLease.Delete() } catch {}
            try { $newLease.Dispose() } catch {}
            $newLease = $null
        }
        throw
    } finally {
        if ($null -ne $stream) { $stream.Dispose() }
    }
}

function Invoke-StateConcurrencyProbe([hashtable]$State) {
    if ($env:EMPLOYEE_ACCEPTANCE_TEST_STATE_CONCURRENCY -ne '1') { return }
    if ([string]::IsNullOrWhiteSpace([string]$ownedRoot) -or [string]::IsNullOrWhiteSpace([string]$statePath)) {
        throw 'STATE_CONCURRENCY_SETUP_FAILED'
    }

    $probePath = Join-Path $ownedRoot '.state-concurrency-verified'
    $readerReadyPath = Join-Path $ownedRoot '.state-concurrency-reader-ready'
    $firstPublishedPath = Join-Path $ownedRoot '.state-concurrency-first-published'
    $reopenReadyPath = Join-Path $ownedRoot '.state-concurrency-reopen-ready'
    $publishCompletePath = Join-Path $ownedRoot '.state-concurrency-publish-complete'
    $readerCode = @'
$path = [Environment]::GetEnvironmentVariable('EMPLOYEE_ACCEPTANCE_READER_PATH')
$marker = [Environment]::GetEnvironmentVariable('EMPLOYEE_ACCEPTANCE_READER_MARKER')
$readerReady = [Environment]::GetEnvironmentVariable('EMPLOYEE_ACCEPTANCE_READER_READY')
$firstPublished = [Environment]::GetEnvironmentVariable('EMPLOYEE_ACCEPTANCE_READER_FIRST')
$reopenReady = [Environment]::GetEnvironmentVariable('EMPLOYEE_ACCEPTANCE_READER_REOPEN')
$publishComplete = [Environment]::GetEnvironmentVariable('EMPLOYEE_ACCEPTANCE_READER_COMPLETE')
$validate = {
    param([string]$text)
    if ([string]::IsNullOrWhiteSpace($text)) { exit 21 }
    try { $value = $text | ConvertFrom-Json } catch { exit 22 }
    if ($null -eq $value -or [string]$value.owner_marker -cne $marker -or
        [string]$value.database -notmatch '^quan_ly_nhan_su_employee_test_[a-f0-9]{12}$' -or
        [string]$value.run_id -notmatch '^[a-f0-9]{12}$' -or
        [string]$value.admin_ma_nv -notmatch '^NV[0-9]{3}$' -or
        [string]$value.admin_email -notmatch '^admin-[a-f0-9]{12}@example\.test$') { exit 23 }
}
$share = [IO.FileShare]::ReadWrite -bor [IO.FileShare]::Delete
$held = $null
try {
    $held = [IO.File]::Open($path, [IO.FileMode]::Open, [IO.FileAccess]::Read, $share)
    $ready = [IO.File]::Open($readerReady, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
    try { $ready.WriteByte(1); $ready.Flush($true) } finally { $ready.Dispose() }
    $deadline = (Get-Date).AddSeconds(10)
    while (-not [IO.File]::Exists($firstPublished) -and (Get-Date) -lt $deadline) { Start-Sleep -Milliseconds 10 }
    if (-not [IO.File]::Exists($firstPublished)) { exit 24 }
    $bytes = New-Object byte[] 65536
    $held.Position = 0
    $count = $held.Read($bytes, 0, $bytes.Length)
    if ($count -le 0) { exit 25 }
    & $validate ([Text.Encoding]::UTF8.GetString($bytes, 0, $count))
    $ready = [IO.File]::Open($reopenReady, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
    try { $ready.WriteByte(1); $ready.Flush($true) } finally { $ready.Dispose() }
} finally {
    if ($null -ne $held) { $held.Dispose() }
}
$deadline = (Get-Date).AddSeconds(10)
while (-not [IO.File]::Exists($publishComplete) -and (Get-Date) -lt $deadline) {
    $stream = $null
    try {
        $stream = [IO.File]::Open($path, [IO.FileMode]::Open, [IO.FileAccess]::Read, $share)
        $bytes = New-Object byte[] 65536
        $count = $stream.Read($bytes, 0, $bytes.Length)
        if ($count -le 0) { exit 26 }
        & $validate ([Text.Encoding]::UTF8.GetString($bytes, 0, $count))
    } finally {
        if ($null -ne $stream) { $stream.Dispose() }
    }
    Start-Sleep -Milliseconds 1
}
if (-not [IO.File]::Exists($publishComplete)) { exit 27 }
exit 0
'@
    $reader = $null
    try {
        $start = New-Object Diagnostics.ProcessStartInfo
        $start.FileName = 'pwsh'; $start.UseShellExecute = $false; $start.CreateNoWindow = $true
        [void]$start.ArgumentList.Add('-NoProfile'); [void]$start.ArgumentList.Add('-Command'); [void]$start.ArgumentList.Add($readerCode)
        $start.Environment['EMPLOYEE_ACCEPTANCE_READER_PATH'] = $statePath
        $start.Environment['EMPLOYEE_ACCEPTANCE_READER_MARKER'] = [string]$State.owner_marker
        $start.Environment['EMPLOYEE_ACCEPTANCE_READER_READY'] = $readerReadyPath
        $start.Environment['EMPLOYEE_ACCEPTANCE_READER_FIRST'] = $firstPublishedPath
        $start.Environment['EMPLOYEE_ACCEPTANCE_READER_REOPEN'] = $reopenReadyPath
        $start.Environment['EMPLOYEE_ACCEPTANCE_READER_COMPLETE'] = $publishCompletePath
        $reader = New-Object Diagnostics.Process; $reader.StartInfo = $start
        if (-not $reader.Start()) { throw 'STATE_CONCURRENCY_READER_START_FAILED' }
        $deadline = (Get-Date).AddSeconds(10)
        while (-not [IO.File]::Exists($readerReadyPath) -and (Get-Date) -lt $deadline) { Start-Sleep -Milliseconds 10 }
        if (-not [IO.File]::Exists($readerReadyPath)) { throw 'STATE_CONCURRENCY_READER_READY_TIMEOUT' }
        $first = @{}
        foreach ($key in $State.Keys) { $first[$key] = $State[$key] }
        $first.phase = 'started'
        Write-AtomicState $first
        [IO.File]::WriteAllText($firstPublishedPath, "published`n", [Text.UTF8Encoding]::new($false))
        $deadline = (Get-Date).AddSeconds(10)
        while (-not [IO.File]::Exists($reopenReadyPath) -and (Get-Date) -lt $deadline) { Start-Sleep -Milliseconds 10 }
        if (-not [IO.File]::Exists($reopenReadyPath)) { throw 'STATE_CONCURRENCY_REOPEN_READY_TIMEOUT' }
        for ($i = 0; $i -lt 159; $i++) {
            $next = @{}
            foreach ($key in $State.Keys) { $next[$key] = $State[$key] }
            $next.phase = if (($i % 2) -eq 0) { 'started' } else { 'starting' }
            Write-AtomicState $next
        }
        [IO.File]::WriteAllText($publishCompletePath, "complete`n", [Text.UTF8Encoding]::new($false))
        if (-not $reader.WaitForExit(10000)) { throw 'STATE_CONCURRENCY_READER_TIMEOUT' }
        if ($reader.ExitCode -ne 0) { throw 'STATE_ATOMIC_CONCURRENCY_FAILED' }
        [IO.File]::WriteAllText($probePath, "passed`n", [Text.UTF8Encoding]::new($false))
    } finally {
        if ($null -ne $reader) {
            if (-not $reader.HasExited) { try { $reader.Kill() } catch {} }
            $reader.Dispose()
        }
    }
}

function Claim-State {
    $script:statePath = Resolve-StatePath
    $script:stateParentLease = [EmployeeAcceptance.NativeMethods+Lease]::Open((Normalize-Path $testingRoot), $false)
    Enter-StateInvocationLock
    $script:stateOwnerMarker = [Guid]::NewGuid().ToString('N')
    $script:stateClaimOwnerMarker = $stateOwnerMarker
    $placeholder = @{ schema = 1; phase = 'starting'; owner_marker = $stateOwnerMarker }
    $stream = $null
    try {
        $script:stateLease = [EmployeeAcceptance.NativeMethods+Lease]::CreateStateRelative($stateParentLease, [IO.Path]::GetFileName($statePath))
        $script:claimedState = $true
        # The CreateNew-relative lease is the durable object identity lock.
        $json = $placeholder | ConvertTo-Json -Compress
        $stream = $stateLease.OpenStream()
        $bytes = [Text.Encoding]::UTF8.GetBytes($json)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Flush($true)
    } catch {
        if ($null -ne $stateLease) {
            $script:stateDeleteRequested = $true
            try { $stateLease.Delete() } catch { Add-CleanupError 'STATE_CLEANUP_FAILED' }
        }
        $script:claimedState = $false
        throw
    } finally {
        if ($null -ne $stream) { $stream.Dispose() }
    }
}

function Snapshot-Environment {
    foreach ($name in $managedEnvironmentNames) {
        $value = [Environment]::GetEnvironmentVariable($name, 'Process')
        $environmentSnapshot[$name] = @{ Exists = $null -ne $value; Value = $value }
    }
}

function Restore-Environment {
    foreach ($name in $managedEnvironmentNames) {
        if (-not $environmentSnapshot.ContainsKey($name)) { continue }
        $old = $environmentSnapshot[$name]
        if ($old.Exists) { [Environment]::SetEnvironmentVariable($name, $old.Value, 'Process') }
        else { [Environment]::SetEnvironmentVariable($name, $null, 'Process') }
    }
}

function Close-StateLeases {
    $failure = $false
    if ($null -ne $stateLease) {
        try {
            if ($stateDeleteRequested) { $stateLease.Delete() }
        } catch { $failure = $true }
        try { $stateLease.Dispose() } catch { $failure = $true }
        $script:stateLease = $null
    }
    if ($null -ne $stateParentLease) {
        try { $stateParentLease.Dispose() } catch { $failure = $true }
        $script:stateParentLease = $null
    }
    if ($null -ne $stateLockLease) {
        try { $stateLockLease.Dispose() } catch { $failure = $true }
        $script:stateLockLease = $null
    }
    if ($failure) { throw 'STATE_LEASE_CLOSE_FAILED' }
}

function Close-OwnedLeases {
    $failure = $false
    if ($null -ne $ownedRootLease) {
        try { $ownedRootLease.Dispose() } catch { $failure = $true }
        $script:ownedRootLease = $null
    }
    if ($null -ne $ownedParentLease) {
        try { $ownedParentLease.Dispose() } catch { $failure = $true }
        $script:ownedParentLease = $null
    }
    if ($failure) { throw 'OWNED_LEASE_CLOSE_FAILED' }
}

function Resolve-MariaDbCredentials {
    Snapshot-Environment
    $env:MARIADB_TEST_ENABLED = '1'
    if ([string]::IsNullOrEmpty($env:MARIADB_TEST_HOST)) { $env:MARIADB_TEST_HOST = '127.0.0.1' }
    if ([string]::IsNullOrEmpty($env:MARIADB_TEST_PORT)) { $env:MARIADB_TEST_PORT = '3306' }
    if ([string]::IsNullOrEmpty($env:MARIADB_TEST_USERNAME)) { $env:MARIADB_TEST_USERNAME = Read-Host 'MariaDB test username' }
    if ($null -eq [Environment]::GetEnvironmentVariable('MARIADB_TEST_PASSWORD', 'Process')) {
        $secure = Read-Host 'MariaDB test password' -AsSecureString
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
        try { $env:MARIADB_TEST_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    }
    if ([string]::IsNullOrEmpty($env:MARIADB_TEST_USERNAME)) { throw 'MARIADB_TEST_USERNAME_MISSING' }
}

function Set-ChildEnvironment([string]$Database, [string]$Id) {
    $env:MARIADB_TEST_DATABASE = $Database
    $bytes = New-Object byte[] 32
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($bytes) } finally { $random.Dispose() }
    $env:APP_ENV = 'testing'; $env:APP_DEBUG = 'false'; $env:APP_KEY = 'base64:' + [Convert]::ToBase64String($bytes)
    $env:APP_URL = 'http://127.0.0.1:8012'; $env:APP_TIMEZONE = 'Asia/Ho_Chi_Minh'
    $env:APP_CONFIG_CACHE = Join-Path $ownedRoot 'config.php'; $env:APP_ROUTES_CACHE = Join-Path $ownedRoot 'routes.php'
    $env:DB_CONNECTION = 'mysql'; $env:DB_HOST = $env:MARIADB_TEST_HOST; $env:DB_PORT = $env:MARIADB_TEST_PORT
    $env:DB_DATABASE = $Database; $env:DB_USERNAME = $env:MARIADB_TEST_USERNAME; $env:DB_PASSWORD = $env:MARIADB_TEST_PASSWORD; $env:DB_SOCKET = ''; $env:DB_TIMEZONE = '+07:00'
    $user = [uri]::EscapeDataString($env:MARIADB_TEST_USERNAME); $pass = [uri]::EscapeDataString($env:MARIADB_TEST_PASSWORD)
    $env:DB_URL = "mysql://$user`:$pass@$($env:MARIADB_TEST_HOST):$($env:MARIADB_TEST_PORT)/$Database"
    $env:NHAN_VIEN_MODULE_ENABLED = 'true'; $env:EMPLOYEE_AVATAR_PREFIX = "nhan-vien/acceptance/$Id/avatars"; $env:EMPLOYEE_ACCEPTANCE_RUN_ID = $Id
    $env:SESSION_DRIVER = 'cookie'; $env:CACHE_STORE = 'array'; $env:QUEUE_CONNECTION = 'sync'; $env:LOG_CHANNEL = 'stderr'
}

function Invoke-Php([string[]]$Arguments) {
    $start = New-Object Diagnostics.ProcessStartInfo
    $start.FileName = $phpExecutable; $start.WorkingDirectory = $repoRoot; $start.UseShellExecute = $false
    $start.CreateNoWindow = $true; $start.RedirectStandardOutput = $true; $start.RedirectStandardError = $true
    foreach ($argument in $Arguments) { [void]$start.ArgumentList.Add($argument) }
    $process = New-Object Diagnostics.Process; $process.StartInfo = $start
    if (-not $process.Start()) { throw 'PHP_CHILD_START_FAILED' }
    $stdout = $process.StandardOutput.ReadToEnd(); $stderr = $process.StandardError.ReadToEnd(); $process.WaitForExit()
    if ($process.ExitCode -ne 0) {
        $safeOutput = ($stdout + "`n" + $stderr)
        if ($safeOutput -match '"error"\s*:\s*"([A-Z0-9_]+)"') { throw $Matches[1] }
        throw 'PHP_CHILD_FAILED'
    }
    return @{ stdout = $stdout; stderr = $stderr }
}

function Invoke-EnvironmentHelper([string]$HelperAction) {
    $result = Invoke-Php @((Join-Path $repoRoot 'tests\Support\EmployeeAcceptanceEnvironment.php'), $HelperAction)
    $payload = $result.stdout.Trim() | ConvertFrom-Json -AsHashtable
    if (-not ($payload -is [hashtable])) { throw 'HELPER_JSON_INVALID' }
    return $payload
}

function Invoke-Bootstrap([hashtable]$Fixture) {
    if ($env:EMPLOYEE_ACCEPTANCE_TEST_BOOTSTRAP_FAIL -eq '1') { throw 'BOOTSTRAP_INJECTED_FAILURE' }
    $arguments = @('artisan', 'employee:bootstrap-demo')
    $map = @{
        department = 'department'; position = 'position'; position_allowance = 'position-allowance'; role = 'role'; admin_name = 'admin-name'; admin_email = 'admin-email'; admin_phone = 'admin-phone'; admin_cccd = 'admin-cccd'; birth_date = 'birth-date'; start_date = 'start-date'; gender = 'gender'; education = 'education'; ethnicity = 'ethnicity'; cccd_place = 'cccd-place'; address_line = 'address-line'; ward = 'ward'; district = 'district'; province = 'province'
    }
    foreach ($key in $map.Keys) { $arguments += "--$($map[$key])=$([string]$Fixture[$key])" }
    $arguments += '--yes'; $arguments += '--require-disposable'
    $guardedDbUrl = [Environment]::GetEnvironmentVariable('DB_URL', 'Process')
    try {
        # BootstrapNhanVienDemo rejects DB_URL overrides; the guarded DB fields
        # remain set and verify-runtime restores the encoded URL immediately after.
        $env:DB_URL = ''
        $result = Invoke-Php $arguments
    } finally {
        [Environment]::SetEnvironmentVariable('DB_URL', $guardedDbUrl, 'Process')
    }
    if ($result.stdout -notmatch '(?m)^Mã nhân viên demo: NV001\s*$') { throw 'BOOTSTRAP_IDENTITY_INVALID' }
}

function Get-SyntheticFixture([string]$Id) {
    $digits = ''
    foreach ($character in $Id.ToCharArray()) {
        $digits += [string]([Convert]::ToInt32($character.ToString(), 16) % 10)
    }
    return @{
        expected_ma_nv = 'NV001'; department = "PB Acceptance $Id"; position = "CV Acceptance $Id"; position_allowance = '0.00'; role = "Quản trị acceptance $Id"; admin_name = 'Quản trị Acceptance'; admin_email = "admin-$Id@example.test"; admin_phone = '09' + $digits.Substring(0, 8); admin_cccd = $digits; birth_date = '1990-01-01'; start_date = '2026-08-12'; gender = '1'; education = 'Đại học'; ethnicity = 'Kinh'; cccd_place = 'Cục CSQLHC'; address_line = '1 Đường Kiểm Thử'; ward = 'Phường Test'; district = 'Quận Test'; province = 'TP Hồ Chí Minh'
    }
}

function Get-ListeningPids {
    $connections = @(Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort 8012 -State Listen -ErrorAction SilentlyContinue)
    return @($connections | ForEach-Object { [int]$_.OwningProcess } | Sort-Object -Unique)
}

function Assert-PortFree {
    $listeners = @(Get-ListeningPids)
    if ($listeners.Count -gt 0) { throw 'PORT_IN_USE' }
}

function Get-ProcessIdentity([int]$ProcessId) {
    $process = Get-Process -Id $ProcessId -ErrorAction SilentlyContinue
    if ($null -eq $process) { return $null }
    $cim = Get-CimInstance Win32_Process -Filter "ProcessId = $ProcessId" -ErrorAction SilentlyContinue
    if ($null -eq $cim -or [string]::IsNullOrWhiteSpace([string]$cim.CommandLine)) { return $null }
    $executable = [string]$process.Path
    if ([string]::IsNullOrWhiteSpace($executable)) { return $null }
    return @{
        pid = $ProcessId
        start_utc = $process.StartTime.ToUniversalTime().ToString('o')
        executable = Normalize-Path $executable
        command_line = [string]$cim.CommandLine
    }
}

function Normalize-StateProcessStart([object]$Value) {
    if ($Value -is [DateTime]) { return $Value.ToUniversalTime().ToString('o') }
    return [string]$Value
}

function Get-ExactCommandArguments([string]$CommandLine) {
    try { return @([EmployeeAcceptance.NativeMethods]::ParseCommandLine($CommandLine)) }
    catch { throw 'ARGV_PARSE_FAILED' }
}

function Test-ExactProcessCommand([hashtable]$Identity, [string]$Executable, [object[]]$Tokens) {
    if ((Normalize-Path $Identity.executable) -cne (Normalize-Path $Executable)) { return $false }
    $actual = Get-ExactCommandArguments ([string]$Identity.command_line)
    $expected = @((Normalize-Path $Executable)) + @($Tokens | ForEach-Object { [string]$_ })
    if ($actual.Count -ne $expected.Count) { return $false }
    for ($index = 0; $index -lt $expected.Count; $index++) {
        $left = [string]$actual[$index]
        $right = [string]$expected[$index]
        if ($index -eq 0) {
            if ((Normalize-Path $left) -cne (Normalize-Path $right)) { return $false }
        } elseif ($left -cne $right) {
            return $false
        }
    }
    return $true
}

function Test-ProcessIdentity([hashtable]$State, [switch]$RequireListener) {
    if (-not $State.ContainsKey('pid') -or -not $State.ContainsKey('process_start_utc') -or -not $State.ContainsKey('executable') -or -not $State.ContainsKey('command_tokens')) { return $false }
    $identity = Get-ProcessIdentity ([int]$State.pid)
    if ($null -eq $identity) { return $false }
    if ($RequireListener -and -not ((Get-ListeningPids) -contains [int]$State.pid)) { return $false }
    if ($identity.start_utc -ne (Normalize-StateProcessStart $State.process_start_utc)) { return $false }
    return Test-ExactProcessCommand $identity ([string]$State.executable) @($State.command_tokens)
}

function Test-TrackedProcessIdentity {
    if ($null -eq $ownedProcess -or $null -eq $ownedProcessIdentity -or $null -eq $ownedCommandTokens) { return $false }
    $identity = Get-ProcessIdentity $ownedProcess.Id
    if ($null -eq $identity -or (Get-ListeningPids) -notcontains $ownedProcess.Id) { return $false }
    if ($identity.start_utc -ne [string]$ownedProcessIdentity.start_utc) { return $false }
    return Test-ExactProcessCommand $identity ([string]$ownedProcessIdentity.executable) @($ownedCommandTokens)
}

function New-OwnedRoot([string]$Database, [string]$Id) {
    Assert-RegularChain $testingRoot -AllowMissingLeaf
    if (-not (Test-Path -LiteralPath $tempParent)) { [void](New-Item -ItemType Directory -Path $tempParent) }
    Assert-RegularChain $tempParent -AllowMissingLeaf
    $root = Normalize-Path (Join-Path $tempParent $Id)
    if (Test-Path -LiteralPath $root) { throw 'TEMP_ROOT_EXISTS' }
    [void](New-Item -ItemType Directory -Path $root)
    $script:ownedRoot = $root
    $marker = Join-Path $root '.owned-by-employee-acceptance'
    [IO.File]::WriteAllText($marker, "$Id`n$Database`n$stateOwnerMarker`n", [Text.UTF8Encoding]::new($false))
    return $root
}

function Assert-OwnedRoot([string]$Root, [string]$Database, [string]$Id, [string]$OwnerMarker = $stateOwnerMarker, [switch]$AllowMissingRoot) {
    if ((Normalize-Path ([IO.Path]::GetDirectoryName($Root))) -cne (Normalize-Path $tempParent)) { throw 'TEMP_ROOT_OUTSIDE_GUARD' }
    Assert-RegularChain $Root -AllowMissingLeaf
    if ($AllowMissingRoot -and $null -eq (Get-Item -LiteralPath $Root -Force -ErrorAction SilentlyContinue)) {
        return
    }
    $marker = Join-Path $Root '.owned-by-employee-acceptance'
    Assert-RegularChain $marker
    if ([IO.File]::ReadAllText($marker, [Text.Encoding]::UTF8) -cne "$Id`n$Database`n$OwnerMarker`n") { throw 'TEMP_MARKER_MISMATCH' }
}

function New-QuarantineLeaf { return '.employee-acceptance-quarantine-' + [Guid]::NewGuid().ToString('N') }

function Read-LeaseText([EmployeeAcceptance.NativeMethods+Lease]$Lease) {
    $stream = $null
    try {
        $stream = $Lease.OpenStream()
        $reader = New-Object IO.StreamReader($stream, [Text.Encoding]::UTF8, $false, 4096, $true)
        $text = $reader.ReadToEnd(); $reader.Dispose(); return $text
    } finally { if ($null -ne $stream) { $stream.Dispose() } }
}

function Open-OwnedRootLeases([string]$Root, [string]$Database, [string]$Id, [string]$OwnerMarker) {
    $parent = [IO.Path]::GetDirectoryName($Root)
    $script:ownedParentLease = [EmployeeAcceptance.NativeMethods+Lease]::Open($parent, $false)
    try {
        $script:ownedRootLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($Root, $false)
        if (-not $ownedRootLease.IsDirectory) { throw 'TEMP_ROOT_NOT_DIRECTORY' }
        $markerLease = [EmployeeAcceptance.NativeMethods+Lease]::Open((Join-Path $Root '.owned-by-employee-acceptance'), $false)
        try {
            if ((Read-LeaseText $markerLease) -cne "$Id`n$Database`n$OwnerMarker`n") { throw 'TEMP_MARKER_MISMATCH' }
        } finally { $markerLease.Dispose() }
    } catch {
        Close-OwnedLeases
        throw
    }
}

function Remove-OwnedEntry([string]$Path, [EmployeeAcceptance.NativeMethods+Lease]$ParentLease, [string]$ParentPath) {
    $entryLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($Path, $true)
    try {
        $identity = $entryLease.Identity
        if ($entryLease.IsReparse) { $entryLease.Delete(); return }
        $leaf = New-QuarantineLeaf
        $entryLease.RenameTo($ParentLease, $leaf)
        $quarantine = Join-Path $ParentPath $leaf
        if ($entryLease.RefreshIdentity() -cne $identity) { throw 'PATH_IDENTITY_CHANGED' }
        if ($entryLease.IsReparse) { $entryLease.Delete(); return }
        if ($entryLease.IsDirectory) { Remove-OwnedDirectory $entryLease $quarantine }
        $entryLease.Delete()
    } finally { $entryLease.Dispose() }
}

function Remove-OwnedDirectory([EmployeeAcceptance.NativeMethods+Lease]$DirectoryLease, [string]$Directory) {
    foreach ($item in @(Get-ChildItem -LiteralPath $Directory -Force)) {
        Remove-OwnedEntry $item.FullName $DirectoryLease $Directory
    }
}

function Remove-OwnedTree([string]$Root, [string]$Database, [string]$Id, [string]$OwnerMarker = $stateOwnerMarker) {
    $rootItem = Get-Item -LiteralPath $Root -Force -ErrorAction SilentlyContinue
    if ($null -eq $rootItem) { return }
    $parent = [IO.Path]::GetDirectoryName($Root)
    Assert-OwnedRoot $Root $Database $Id $OwnerMarker
    $parentLease = if ($null -ne $ownedParentLease) { $ownedParentLease } else { [EmployeeAcceptance.NativeMethods+Lease]::Open($parent, $false) }
    $ownsParentLease = ($null -eq $ownedParentLease)
    $rootLease = $null
    try {
        $rootLease = if ($null -ne $ownedRootLease) { $ownedRootLease } else { [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($Root, $true) }
        if ($rootLease.IsReparse -or -not $rootLease.IsDirectory) { throw 'TEMP_REPARSE_ENTRY' }
        $markerLease = [EmployeeAcceptance.NativeMethods+Lease]::Open((Join-Path $Root '.owned-by-employee-acceptance'), $false)
        try {
            if ((Read-LeaseText $markerLease) -cne "$Id`n$Database`n$OwnerMarker`n") { throw 'TEMP_MARKER_MISMATCH' }
        } finally { $markerLease.Dispose() }
        $identity = $rootLease.Identity
        $leaf = New-QuarantineLeaf
        $rootLease.RenameTo($parentLease, $leaf)
        $quarantine = Join-Path $parent $leaf
        if ($rootLease.RefreshIdentity() -cne $identity -or $rootLease.IsReparse) { throw 'TEMP_REPARSE_ENTRY' }
        Remove-OwnedDirectory $rootLease $quarantine
        $rootLease.Delete()
    } finally {
        if ($null -ne $rootLease -and $null -eq $ownedRootLease) { $rootLease.Dispose() }
        if ($ownsParentLease) { $parentLease.Dispose() }
    }
}

function Ensure-StorageLink {
    Assert-RegularChain $publicPath -AllowMissingLeaf
    Assert-RegularChain $storageTargetPath -AllowMissingLeaf
    $existingLink = Get-Item -LiteralPath $publicStoragePath -Force -ErrorAction SilentlyContinue
    if ($null -ne $existingLink) {
        if (-not (Test-Reparse $publicStoragePath)) { throw 'STORAGE_LINK_NOT_LINK' }
        if ((Get-StorageLinkTarget) -cne (Normalize-Path $storageTargetPath)) { throw 'STORAGE_LINK_TARGET_INVALID' }
        return $false
    }
    [void](Invoke-Php @('artisan', 'storage:link'))
    $script:currentInvocationStorageLink = $true
    $script:ownedStorageLink = $true
    if (-not (Test-Path -LiteralPath $publicStoragePath) -or -not (Test-Reparse $publicStoragePath)) { throw 'STORAGE_LINK_CREATE_FAILED' }
    if ((Get-StorageLinkTarget) -cne (Normalize-Path $storageTargetPath)) { throw 'STORAGE_LINK_TARGET_INVALID' }
    $script:ownedStorageLinkIdentity = Get-StorageLinkIdentity
    Write-StorageLinkOwnershipMarker $ownedStorageLinkIdentity
    return $true
}

function Get-StorageLinkIdentity {
    $linkLease = $null
    try {
        $linkLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($publicStoragePath, $true)
        if (-not $linkLease.IsReparse) { throw 'STORAGE_LINK_NOT_LINK' }
        return [string]$linkLease.FullIdentity
    } finally {
        if ($null -ne $linkLease) { $linkLease.Dispose() }
    }
}

function Open-OwnedStorageMarker {
    if ([string]::IsNullOrWhiteSpace([string]$ownedRoot) -or
        $null -eq (Get-Item -LiteralPath $ownedRoot -Force -ErrorAction SilentlyContinue)) {
        throw 'STORAGE_LINK_OWNERSHIP_INVALID'
    }
    $rootLease = $ownedRootLease
    $ownsRootLease = $false
    if ($null -eq $rootLease) {
        $rootLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($ownedRoot, $false)
        $ownsRootLease = $true
    }
    try {
        return [EmployeeAcceptance.NativeMethods+Lease]::OpenRelative($rootLease, '.owned-storage-link', $false)
    } finally {
        if ($ownsRootLease) { $rootLease.Dispose() }
    }
}

function Write-StorageLinkOwnershipMarker([string]$Identity) {
    $rootLease = $ownedRootLease
    $ownsRootLease = $false
    $markerLease = $null
    $stream = $null
    if ($null -eq $rootLease) {
        $rootLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($ownedRoot, $false)
        $ownsRootLease = $true
    }
    try {
        $markerLease = [EmployeeAcceptance.NativeMethods+Lease]::CreateNewRelative($rootLease, '.owned-storage-link')
        if ($env:EMPLOYEE_ACCEPTANCE_TEST_STORAGE_LINK_MARKER_FAIL -eq '1') { throw 'STORAGE_LINK_MARKER_INJECTED_FAILURE' }
        $stream = $markerLease.OpenStream()
        $text = "$runId`n$databaseName`n$stateOwnerMarker`n$Identity`n"
        $bytes = [Text.UTF8Encoding]::new($false).GetBytes($text)
        $stream.Write($bytes, 0, $bytes.Length); $stream.Flush($true)
    } finally {
        if ($null -ne $stream) { $stream.Dispose() }
        if ($null -ne $markerLease) { $markerLease.Dispose() }
        if ($ownsRootLease -and $null -ne $rootLease) { $rootLease.Dispose() }
    }
}

function Assert-StorageLinkOwnership([string]$ExpectedIdentity) {
    if ($ExpectedIdentity -notmatch '^[a-f0-9]{16}:[a-f0-9]{16}:[a-f0-9]{16}$') {
        throw 'STORAGE_LINK_OWNERSHIP_INVALID'
    }
    $markerLease = $null
    try {
        $markerLease = Open-OwnedStorageMarker
        $marker = Read-LeaseText $markerLease
        $expected = "$runId`n$databaseName`n$stateOwnerMarker`n$ExpectedIdentity`n"
        if ($marker -cne $expected) { throw 'STORAGE_LINK_OWNERSHIP_INVALID' }
    } finally {
        if ($null -ne $markerLease) { $markerLease.Dispose() }
    }
}

function Get-StorageLinkTarget {
    $item = Get-Item -LiteralPath $publicStoragePath -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -eq 0) { throw 'STORAGE_LINK_NOT_LINK' }
    $target = $item.Target
    if ($target -is [array]) {
        if ($target.Count -ne 1) { throw 'STORAGE_LINK_TARGET_INVALID' }
        $target = $target[0]
    }
    if ([string]::IsNullOrWhiteSpace([string]$target)) {
        $target = (Resolve-Path -LiteralPath $publicStoragePath).Path
    }
    if (-not [IO.Path]::IsPathRooted([string]$target)) {
        $target = [IO.Path]::GetFullPath([string]$target, [IO.Path]::GetDirectoryName($publicStoragePath))
    }
    return Normalize-Path ([string]$target)
}

function Remove-OwnedStorageLink {
    if (-not $ownedStorageLink) { return }
    Assert-StorageLinkOwnership ([string]$ownedStorageLinkIdentity)
    $parent = [IO.Path]::GetDirectoryName($publicStoragePath)
    $parentLease = [EmployeeAcceptance.NativeMethods+Lease]::Open($parent, $false)
    $linkLease = $null
    try {
        $linkLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($publicStoragePath, $true)
        if (-not $linkLease.IsReparse -or $linkLease.FullIdentity -cne [string]$ownedStorageLinkIdentity) {
            throw 'STORAGE_LINK_OWNERSHIP_INVALID'
        }
        if ((Get-StorageLinkTarget) -cne (Normalize-Path $storageTargetPath)) { throw 'STORAGE_LINK_TARGET_INVALID' }
        [void]$linkLease.RefreshIdentity()
        if ($linkLease.FullIdentity -cne [string]$ownedStorageLinkIdentity) {
            throw 'STORAGE_LINK_OWNERSHIP_INVALID'
        }
        $linkLease.Delete()
    } finally {
        if ($null -ne $linkLease) { $linkLease.Dispose() }
        $parentLease.Dispose()
    }
}

function Remove-CurrentInvocationStorageLink {
    if (-not $currentInvocationStorageLink) { return }
    if ($ownedStorageLinkIdentity -notmatch '^[a-f0-9]{16}:[a-f0-9]{16}:[a-f0-9]{16}$') {
        throw 'STORAGE_LINK_CURRENT_OWNERSHIP_INVALID'
    }
    $parent = [IO.Path]::GetDirectoryName($publicStoragePath)
    $parentLease = [EmployeeAcceptance.NativeMethods+Lease]::Open($parent, $false)
    $linkLease = $null
    try {
        $linkLease = [EmployeeAcceptance.NativeMethods+Lease]::OpenForDelete($publicStoragePath, $true)
        if (-not $linkLease.IsReparse -or $linkLease.FullIdentity -cne [string]$ownedStorageLinkIdentity) {
            throw 'STORAGE_LINK_CURRENT_OWNERSHIP_INVALID'
        }
        if ((Get-StorageLinkTarget) -cne (Normalize-Path $storageTargetPath)) {
            throw 'STORAGE_LINK_CURRENT_TARGET_INVALID'
        }
        [void]$linkLease.RefreshIdentity()
        if ($linkLease.FullIdentity -cne [string]$ownedStorageLinkIdentity) {
            throw 'STORAGE_LINK_CURRENT_OWNERSHIP_INVALID'
        }
        $linkLease.Delete()
    } finally {
        if ($null -ne $linkLease) { $linkLease.Dispose() }
        $parentLease.Dispose()
    }
}

function Start-Server([string]$Id) {
    Assert-PortFree
    $arguments = @('-S', '127.0.0.1:8012', '-t', $publicPath, $routerPath)
    $process = Start-Process -FilePath $phpExecutable -ArgumentList $arguments -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru
    $script:ownedProcess = $process
    $script:ownedCommandTokens = $arguments
    Start-Sleep -Milliseconds 50
    $script:ownedProcessIdentity = Get-ProcessIdentity $process.Id
    $deadline = [DateTime]::UtcNow.AddSeconds(10)
    do {
        Start-Sleep -Milliseconds 100
        if ($process.HasExited) { throw 'PHP_SERVER_EXITED' }
        $identity = Get-ProcessIdentity $process.Id
        if ($null -eq $identity -or -not (Test-ExactProcessCommand $identity $phpExecutable $arguments) -or (Get-ListeningPids) -notcontains $process.Id) { continue }
        $health = Invoke-WebRequest -Uri "http://127.0.0.1:8012/_employee_acceptance_health/$Id" -UseBasicParsing -TimeoutSec 1 -ErrorAction SilentlyContinue
        if ($null -eq $health) { continue }
        $payload = $health.Content | ConvertFrom-Json
        if ($null -eq $payload -or [string]$payload.run_id -cne $Id) { continue }
        $applicationPath = if ($env:EMPLOYEE_ACCEPTANCE_TEST_APP_MARKER_FAIL -eq '1') { '/_employee_acceptance_broken' } else { '/dang-nhap' }
        $application = Invoke-WebRequest -Uri ("http://127.0.0.1:8012" + $applicationPath) -UseBasicParsing -TimeoutSec 1 -ErrorAction SilentlyContinue
        if ($null -eq $application -or [int]$application.StatusCode -ne 200 -or $application.Content -notmatch 'data-login-form' -or $application.Content -notmatch 'Đăng nhập') {
            throw 'APPLICATION_ROUTE_HEALTH_FAILED'
        }
        $script:ownedProcessIdentity = $identity
        return @{
            process = $process
            identity = $identity
            command_tokens = $arguments
        }
    } while ([DateTime]::UtcNow -lt $deadline)
    throw 'PHP_SERVER_HEALTH_TIMEOUT'
}

function Stop-OwnedProcess([hashtable]$State) {
    if (-not (Test-ProcessIdentity $State -RequireListener)) {
        throw 'PROCESS_IDENTITY_MISMATCH'
    }
    Stop-Process -Id ([int]$State.pid) -Force
    $deadline = [DateTime]::UtcNow.AddSeconds(5)
    while ([DateTime]::UtcNow -lt $deadline) {
        if ($null -eq (Get-Process -Id ([int]$State.pid) -ErrorAction SilentlyContinue)) { return }
        Start-Sleep -Milliseconds 100
    }
    throw 'PROCESS_STOP_TIMEOUT'
}

function Write-StartedState([hashtable]$Server, [hashtable]$Fixture) {
    $identity = $Server.identity
    $state = @{
        schema = 1; phase = 'started'; owner_marker = $stateOwnerMarker; database = $databaseName; run_id = $runId
        admin_ma_nv = $Fixture.expected_ma_nv; admin_email = $Fixture.admin_email; pid = $Server.process.Id
        process_start_utc = $identity.start_utc; executable = $identity.executable; command_tokens = @($Server.command_tokens)
        port = 8012; owns_storage_link = [bool]$ownedStorageLink
    }
    if ($ownedStorageLink) { $state.storage_link_identity = [string]$ownedStorageLinkIdentity }
    $script:stateOwnerMarker = [string]$state.owner_marker
    Write-AtomicState $state
    return $state
}

function Cleanup-StartResources {
    $errors = @()
    if ($null -ne $ownedProcess -and $null -ne $databaseName -and $null -ne $runId) {
        try {
            $state = if (Test-Path -LiteralPath $statePath) { Read-State } else { $null }
            if ($null -ne $state -and (Test-ProcessIdentity $state -RequireListener)) {
                Stop-OwnedProcess $state
            } elseif (Test-TrackedProcessIdentity) {
                Stop-Process -Id $ownedProcess.Id -Force
            }
        } catch { $errors += 'PROCESS_CLEANUP_FAILED' }
    }
    if ($null -ne $databaseName -and $null -ne $runId) {
        if ($null -eq $ownedRoot) { $script:ownedRoot = Normalize-Path (Join-Path $tempParent $runId) }
        try { Set-ChildEnvironment $databaseName $runId } catch { $errors += 'CHILD_ENVIRONMENT_FAILED' }
        try { [void](Invoke-EnvironmentHelper 'cleanup-uploads') } catch { $errors += 'UPLOAD_CLEANUP_FAILED' }
        try { [void](Invoke-EnvironmentHelper 'drop') } catch { $errors += 'DATABASE_CLEANUP_FAILED' }
    }
    try {
        if ($currentInvocationStorageLink) {
            Remove-CurrentInvocationStorageLink
        } else {
            Remove-OwnedStorageLink
        }
    } catch { $errors += 'LINK_CLEANUP_FAILED' }
    if ($null -ne $ownedRoot -and $null -ne $databaseName -and $null -ne $runId) {
        try { Remove-OwnedTree $ownedRoot $databaseName $runId } catch { $errors += 'TEMP_CLEANUP_FAILED' }
    }
    if ($claimedState -and $null -ne $stateLease) {
        try { Remove-ClaimedStateIfOwned } catch { $errors += 'STATE_CLEANUP_FAILED' }
    }
    if ($errors.Count -gt 0) {
        foreach ($code in $errors) { Add-CleanupError $code }
        throw 'START_CLEANUP_INCOMPLETE'
    }
}

function Remove-ClaimedStateIfOwned {
    if ([string]::IsNullOrWhiteSpace([string]$statePath) -or [string]::IsNullOrWhiteSpace([string]$stateClaimOwnerMarker)) { return }
    Enter-StateInvocationLock
    if ($stateDeleteRequested -or $stateRemoved) { return }
    $lease = $null
    try {
        $lease = [EmployeeAcceptance.NativeMethods+Lease]::OpenStateVerifyRelative($stateParentLease, [IO.Path]::GetFileName($statePath))
        $current = (Read-LeaseText $lease) | ConvertFrom-Json -AsHashtable
        if (-not ($current -is [hashtable]) -or [string]$current.owner_marker -cne [string]$stateClaimOwnerMarker) {
            throw 'STATE_OWNERSHIP_INVALID'
        }
        if ($null -ne $stateLease -and $lease.FullIdentity -cne $stateLease.FullIdentity) {
            throw 'STATE_IDENTITY_MISMATCH'
        }
        if ($null -eq $stateLease) { throw 'STATE_LEASE_MISSING' }
        $stateLease.Delete()
        $stateLease.Dispose()
        $script:stateLease = $null
        $script:stateDeleteRequested = $false
        $script:stateRemoved = $true
        $after = $null
        try {
            $after = [EmployeeAcceptance.NativeMethods+Lease]::OpenStateVerifyRelative($stateParentLease, [IO.Path]::GetFileName($statePath))
            throw 'STATE_IDENTITY_MISMATCH'
        } catch {
            if ($null -ne $after) { throw }
            if ($_.Exception.Message -notmatch 'NATIVE_RELATIVE_OPEN_FAILED_C0000034') { throw 'STATE_CLEANUP_FAILED' }
        } finally {
            if ($null -ne $after) { $after.Dispose() }
        }
    } finally {
        if ($null -ne $lease) { $lease.Dispose() }
    }
}

function Invoke-Start {
    Assert-PortFree
    Claim-State
    $created = Invoke-EnvironmentHelper 'create'
        $script:databaseName = [string]$created.database
        if ($databaseName -notmatch '^quan_ly_nhan_su_employee_test_[a-f0-9]{12}$') { throw 'CREATED_DATABASE_INVALID' }
        $script:runId = $databaseName.Substring($databaseName.Length - 12)
        $partial = @{ schema = 1; phase = 'starting'; owner_marker = $stateOwnerMarker; database = $databaseName; run_id = $runId; admin_ma_nv = 'NV001'; admin_email = "admin-$runId@example.test"; port = 8012; owns_storage_link = $false }
        Write-AtomicState $partial
        New-OwnedRoot $databaseName $runId | Out-Null
        Set-ChildEnvironment $databaseName $runId
        [void](Invoke-EnvironmentHelper 'verify-runtime')
        $fixture = Get-SyntheticFixture $runId
        Invoke-Bootstrap $fixture
        [void](Invoke-EnvironmentHelper 'verify-runtime')
        [void](Invoke-EnvironmentHelper 'seed-roles')
        [void](Invoke-EnvironmentHelper 'verify-runtime')
        $script:ownedStorageLink = Ensure-StorageLink
        $partial.owns_storage_link = [bool]$ownedStorageLink
        if ($ownedStorageLink) { $partial.storage_link_identity = [string]$ownedStorageLinkIdentity }
        Write-AtomicState $partial
        $server = Start-Server $runId
        $state = Write-StartedState $server $fixture
        Invoke-StateConcurrencyProbe $state
    return @{ state_file = $statePath; url = 'http://127.0.0.1:8012'; admin_ma_nv = $state.admin_ma_nv; admin_email = $state.admin_email }
}

function Invoke-AddDependency {
    Enter-StateInvocationLock
    $state = Read-State
    $script:stateOwnerMarker = [string]$state.owner_marker
    $script:ownedRoot = Normalize-Path (Join-Path $tempParent ([string]$state.run_id))
    Assert-OwnedRoot $ownedRoot ([string]$state.database) ([string]$state.run_id) $stateOwnerMarker
    Open-OwnedRootLeases $ownedRoot ([string]$state.database) ([string]$state.run_id) $stateOwnerMarker
    $script:databaseName = [string]$state.database
    $script:runId = [string]$state.run_id
    Set-ChildEnvironment $databaseName $runId
    $env:EMPLOYEE_ACCEPTANCE_MA_NV = $Employee
    $env:EMPLOYEE_ACCEPTANCE_DEPENDENCY = $Dependency
    try { [void](Invoke-Php @(Join-Path $repoRoot 'tests\Support\PrepareEmployeeAcceptanceDependency.php')) }
    finally { Restore-Environment }
    return @{ ok = $true; employee = $Employee; dependency = $Dependency }
}

function Invoke-AssignRole {
    Enter-StateInvocationLock
    $state = Read-State
    $script:stateOwnerMarker = [string]$state.owner_marker
    $script:ownedRoot = Normalize-Path (Join-Path $tempParent ([string]$state.run_id))
    Assert-OwnedRoot $ownedRoot ([string]$state.database) ([string]$state.run_id) $stateOwnerMarker
    Open-OwnedRootLeases $ownedRoot ([string]$state.database) ([string]$state.run_id) $stateOwnerMarker
    $script:databaseName = [string]$state.database
    $script:runId = [string]$state.run_id
    Set-ChildEnvironment $databaseName $runId
    $env:EMPLOYEE_ACCEPTANCE_MA_NV = $Employee; $env:EMPLOYEE_ACCEPTANCE_ROLE_ALIAS = $RoleAlias
    try { [void](Invoke-EnvironmentHelper 'assign-role') }
    finally { Restore-Environment }
    return @{ ok = $true; employee = $Employee; role = $RoleAlias }
}

function Invoke-Stop {
    Enter-StateInvocationLock
    $script:currentInvocationStorageLink = $false
    $state = Read-State
    Invoke-TestStateSwapHold
    $script:databaseName = [string]$state.database; $script:runId = [string]$state.run_id; $script:stateOwnerMarker = [string]$state.owner_marker; $script:stateClaimOwnerMarker = $stateOwnerMarker; $script:claimedState = $true; $script:ownedStorageLink = [bool]$state.owns_storage_link; $script:ownedStorageLinkIdentity = if ($state.ContainsKey('storage_link_identity')) { [string]$state.storage_link_identity } else { $null }
    $script:ownedRoot = Normalize-Path (Join-Path $tempParent $runId)
    $ownedRootExists = $null -ne (Get-Item -LiteralPath $ownedRoot -Force -ErrorAction SilentlyContinue)
    Assert-OwnedRoot $ownedRoot $databaseName $runId $stateOwnerMarker -AllowMissingRoot
    if ($ownedRootExists) { Open-OwnedRootLeases $ownedRoot $databaseName $runId $stateOwnerMarker }
    $stopError = $null
    Set-ChildEnvironment $databaseName $runId
    try {
        try { Stop-OwnedProcess $state } catch { $stopError = 'PROCESS_IDENTITY_MISMATCH' }
        try { [void](Invoke-EnvironmentHelper 'cleanup-uploads') } catch { if ($null -eq $stopError) { $stopError = 'UPLOAD_CLEANUP_FAILED' } }
        try { [void](Invoke-EnvironmentHelper 'drop') } catch { if ($null -eq $stopError) { $stopError = 'DATABASE_CLEANUP_FAILED' } }
        try { Remove-OwnedStorageLink } catch {
            $detail = [string]$_.Exception.Message
            $messages = @($detail)
            $inner = $_.Exception.InnerException
            while ($null -ne $inner) { $messages += [string]$inner.Message; $inner = $inner.InnerException }
            $safeDetail = $messages | Where-Object { $_ -match '^(?:STORAGE_LINK|NATIVE)_[A-Z0-9_]+$' } | Select-Object -First 1
            $script:linkFailureDetail = if ($null -ne $safeDetail) { [string]$safeDetail } else { 'LINK_CLEANUP_FAILED' }
            if ($null -eq $stopError) { $stopError = $linkFailureDetail }
        }
        try { Remove-OwnedTree $ownedRoot $databaseName $runId } catch { if ($null -eq $stopError) { $stopError = 'TEMP_CLEANUP_FAILED' } }
        try { Remove-ClaimedStateIfOwned } catch { if ($null -eq $stopError) { $stopError = 'STATE_CLEANUP_FAILED' } }
    } finally { Restore-Environment }
    if ($null -ne $stopError) { $script:actionFailure = $stopError; throw $stopError }
    return @{ ok = $true; stopped = $true }
}

function Assert-ActionParameters {
    $allowed = switch ($Action) {
        'Start' { @('Action', 'StateFile', 'EnableDisposableMariaDb', 'SmokeTest') }
        'AddDependency' { @('Action', 'StateFile', 'EnableDisposableMariaDb', 'Employee', 'Dependency') }
        'AssignRole' { @('Action', 'StateFile', 'EnableDisposableMariaDb', 'Employee', 'RoleAlias') }
        'Stop' { @('Action', 'StateFile', 'EnableDisposableMariaDb') }
    }
    foreach ($key in $boundParameterNames) { if ($allowed -notcontains $key) { throw 'PARAMETER_SET_INVALID' } }
    if (-not $EnableDisposableMariaDb) { throw 'DISPOSABLE_SWITCH_REQUIRED' }
    if ($Action -ne 'Start' -and $SmokeTest) { throw 'SMOKE_PARAMETER_INVALID' }
    if ($Action -eq 'AddDependency' -and ([string]::IsNullOrEmpty($Employee) -or [string]::IsNullOrEmpty($Dependency))) { throw 'DEPENDENCY_PARAMETERS_REQUIRED' }
    if ($Action -eq 'AssignRole' -and ([string]::IsNullOrEmpty($Employee) -or [string]::IsNullOrEmpty($RoleAlias))) { throw 'ROLE_PARAMETERS_REQUIRED' }
}

try {
    Assert-ActionParameters
    $script:statePath = Resolve-StatePath
    Resolve-MariaDbCredentials
    $result = switch ($Action) {
        'Start' {
            $startResult = Invoke-Start
            if ($SmokeTest) {
                $smokeState = Read-State
                try { [void](Invoke-Stop) } catch { throw }
                @{ ok = $true; smoke_test = $true; url = $startResult.url }
            } else { $startResult }
        }
        'AddDependency' { Invoke-AddDependency }
        'AssignRole' { Invoke-AssignRole }
        'Stop' { Invoke-Stop }
    }
    Close-OwnedLeases
    Close-StateLeases
    Restore-Environment
    $result | ConvertTo-Json -Compress
    exit 0
} catch {
    $failureMessage = [string]$_.Exception.Message
    try {
        if ($Action -eq 'Start' -and $claimedState) { Cleanup-StartResources }
    } catch { Add-CleanupError 'START_CLEANUP_FAILED' }
    try {
        if ($Action -eq 'Start' -and $claimedState) { Remove-ClaimedStateIfOwned }
    } catch { Add-CleanupError 'CLAIMED_STATE_CLEANUP_FAILED' }
    try { Close-OwnedLeases } catch { Add-CleanupError 'OWNED_LEASE_CLOSE_FAILED' }
    try { Close-StateLeases } catch { Add-CleanupError 'STATE_LEASE_CLOSE_FAILED' }
    try { Restore-Environment } catch { Add-CleanupError 'RESTORE_ENVIRONMENT_FAILED' }
    $failureCode = if ($cleanupErrors.Count -gt 0) {
        'ACCEPTANCE_HARNESS_CLEANUP_FAILED'
    } elseif ($failureMessage -match 'STATE_LOCKED') {
        'STATE_LOCKED'
    } elseif ($failureMessage -match 'STATE_LOCK_CREATE_FAILED') {
        'STATE_LOCK_CREATE_FAILED'
    } elseif ($failureMessage -match 'NATIVE_RELATIVE_OPEN_FAILED_([0-9A-F]{8})') {
        'NATIVE_OPEN_FAILED_' + $Matches[1]
    } elseif ($failureMessage -match '(NATIVE_[A-Z0-9_]+)') {
        'NATIVE_FAILURE_' + $Matches[1]
    } elseif ($null -ne $linkFailureDetail) {
        [string]$linkFailureDetail
    } elseif ($null -ne $actionFailure -and [string]$actionFailure -in @('PROCESS_IDENTITY_MISMATCH', 'LINK_CLEANUP_FAILED', 'UPLOAD_CLEANUP_FAILED', 'DATABASE_CLEANUP_FAILED', 'TEMP_CLEANUP_FAILED', 'STATE_CLEANUP_FAILED')) {
        [string]$actionFailure
    } elseif ($failureMessage -match '^[A-Z][A-Z0-9_]{2,}$') {
        $failureMessage
    } else {
        'ACCEPTANCE_HARNESS_FAILED'
    }
    @{ ok = $false; error = $failureCode } | ConvertTo-Json -Compress | Write-Output
    exit 1
}
