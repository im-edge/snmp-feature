%define revision 1
%define git_version %( git describe --tags | cut -c2- | tr -s '-' '+')
%define git_hash %( git rev-parse --short HEAD )
%define basedir         %{_datadir}/imedge-features/snmp
%define bindir          %{_bindir}
%undefine __brp_mangle_shebangs

Name:           imedge-feature-snmp
Version:        %{git_version}
Release:        %{revision}%{?dist}
Summary:        IMEdge SNMP Feature
Group:          Applications/System
License:        MIT
URL:            https://github.com/im-edge/snmp-feature
Source0:        https://github.com/im-edge/snmp-feature/archive/%{git_hash}.tar.gz
BuildArch:      noarch
BuildRoot:      %{_tmppath}/%{name}-%{git_version}-%{release}
Packager:       Thomas Gelf <thomas@gelf.net>
Requires:       imedge-node

%description
This package provides SNMP capabilities for your IMEdge Node. It comes with a
bunch of predefined polling scenarios, generic API methods for your interactive
components and an advanced and autonomous scheduling and polling logic.

%prep

%build

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}
mkdir -p %{buildroot}%{basedir}
cd - # ???
cp -pr  src vendor feature.php %{buildroot}%{basedir}/

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root)
%{basedir}

%changelog
* Mon Jan 13 2025 Thomas Gelf <thomas@gelf.net> 0.0.0
- Initial packaging
