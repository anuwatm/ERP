export type BranchFormData = {
    name: string;
    phone: string;
    address: string;
    is_head_office: boolean;
};

export type DivisionFormData = {
    branch_id: string;
    name: string;
};

export type DepartmentFormData = {
    branch_id: string;
    division_id: string;
    name: string;
};

export function isBranchDirty(
    data: BranchFormData,
    defaults: BranchFormData = {
        name: '',
        phone: '',
        address: '',
        is_head_office: false,
    },
): boolean {
    return (
        data.name !== defaults.name ||
        data.phone !== defaults.phone ||
        data.address !== defaults.address ||
        Boolean(data.is_head_office) !== Boolean(defaults.is_head_office)
    );
}

export function isDivisionDirty(
    data: DivisionFormData,
    defaults: DivisionFormData = {
        branch_id: '',
        name: '',
    },
): boolean {
    return data.branch_id !== defaults.branch_id || data.name !== defaults.name;
}

export function isDepartmentDirty(
    data: DepartmentFormData,
    defaults: DepartmentFormData = {
        branch_id: '',
        division_id: '',
        name: '',
    },
): boolean {
    return (
        data.branch_id !== defaults.branch_id ||
        data.division_id !== defaults.division_id ||
        data.name !== defaults.name
    );
}
